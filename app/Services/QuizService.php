<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuizService
{
    /*
    |--------------------------------------------------------------------------
    | CREATE QUIZ
    |--------------------------------------------------------------------------
    */

    public function create(
        array $data,
        User $admin
    ): Quiz {
        return DB::transaction(function () use (
            $data,
            $admin
        ) {
            $questions = $data['questions'];

            $totalMarks = collect($questions)
                ->sum(
                    fn ($question) =>
                        (int) $question['marks']
                );

            $quiz = Quiz::create([
                'batch_id' => $data['batch_id'],
                'created_by' => $admin->id,

                'title' => $data['title'],

                'slug' => $this->generateUniqueSlug(
                    $data['title']
                ),

                'description' =>
                    $data['description'] ?? null,

                'duration_minutes' =>
                    $data['duration_minutes'],

                'total_marks' =>
                    $totalMarks,

                'passing_marks' =>
                    $data['passing_marks'],

                'attempts_allowed' =>
                    $data['attempts_allowed'],

                'shuffle_questions' =>
                    $data['shuffle_questions'] ?? false,

                'shuffle_options' =>
                    $data['shuffle_options'] ?? false,

                'auto_submit' =>
                    $data['auto_submit'] ?? true,

                'show_result' =>
                    $data['show_result'] ?? true,

                'show_correct_answers' =>
                    $data['show_correct_answers'] ?? true,

                'status' =>
                    $data['status'] ?? 'draft',
            ]);

            $this->createQuestions(
                $quiz,
                $questions
            );

            return $this->loadAdminQuiz(
                $quiz->fresh()
            );
        }, 3);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE QUIZ
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | We DO NOT delete all questions anymore.
    |
    | Existing question IDs are preserved so existing
    | quiz_answers continue to point to valid questions.
    |
    */

    public function update(
        Quiz $quiz,
        array $data
    ): Quiz {
        return DB::transaction(function () use (
            $quiz,
            $data
        ) {
            $quiz = Quiz::query()
                ->whereKey($quiz->id)
                ->lockForUpdate()
                ->firstOrFail();

            $questions = $data['questions'];

            /*
            |--------------------------------------------------------------------------
            | Calculate new total marks
            |--------------------------------------------------------------------------
            */

            $totalMarks = collect($questions)
                ->sum(
                    fn ($question) =>
                        (int) $question['marks']
                );


            /*
            |--------------------------------------------------------------------------
            | Update Quiz
            |--------------------------------------------------------------------------
            */

            $updateData = [
                'batch_id' => $data['batch_id'],

                'title' => $data['title'],

                'description' =>
                    $data['description'] ?? null,

                'duration_minutes' =>
                    $data['duration_minutes'],

                'total_marks' =>
                    $totalMarks,

                'passing_marks' =>
                    $data['passing_marks'],

                'attempts_allowed' =>
                    $data['attempts_allowed'],

                'shuffle_questions' =>
                    $data['shuffle_questions'] ?? false,

                'shuffle_options' =>
                    $data['shuffle_options'] ?? false,

                'auto_submit' =>
                    $data['auto_submit'] ?? true,

                'show_result' =>
                    $data['show_result'] ?? true,

                'show_correct_answers' =>
                    $data['show_correct_answers'] ?? true,

                'status' =>
                    $data['status'] ?? 'draft',
            ];


            /*
            |--------------------------------------------------------------------------
            | Update slug only if title changed
            |--------------------------------------------------------------------------
            */

            if (
                $data['title'] !== $quiz->title
            ) {
                $updateData['slug'] =
                    $this->generateUniqueSlug(
                        $data['title'],
                        $quiz->id
                    );
            }


            $quiz->update($updateData);


            /*
            |--------------------------------------------------------------------------
            | Sync Questions
            |--------------------------------------------------------------------------
            */

            $this->updateQuestions(
                $quiz,
                $questions
            );


            /*
            |--------------------------------------------------------------------------
            | Return updated quiz
            |--------------------------------------------------------------------------
            */

            return $this->loadAdminQuiz(
                $quiz->fresh()
            );
        }, 3);
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE QUIZ - HARD DELETE
    |--------------------------------------------------------------------------
    |
    | Everything related to the quiz is permanently deleted.
    |
    */

    public function delete(
        Quiz $quiz
    ): void {
        DB::transaction(function () use (
            $quiz
        ) {
            $quiz = Quiz::query()
                ->whereKey($quiz->id)
                ->lockForUpdate()
                ->firstOrFail();


            /*
            |--------------------------------------------------------------------------
            | Get all questions
            |--------------------------------------------------------------------------
            */

            $questions = $quiz->questions()
                ->with('options')
                ->get();


            /*
            |--------------------------------------------------------------------------
            | Delete question images
            |--------------------------------------------------------------------------
            */

            foreach ($questions as $question) {
                if (
                    $question->question_image
                ) {
                    Storage::disk('public')->delete(
                        $question->question_image
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Delete quiz answers
            |--------------------------------------------------------------------------
            |
            | quiz_answers.quiz_question_id
            | is restrictive, so answers must be removed
            | before questions.
            |
            */

            $attemptIds = $quiz->attempts()
                ->pluck('id');

            if ($attemptIds->isNotEmpty()) {
                DB::table('quiz_answers')
                    ->whereIn(
                        'attempt_id',
                        $attemptIds
                    )
                    ->delete();
            }


            /*
            |--------------------------------------------------------------------------
            | Delete quiz attempts
            |--------------------------------------------------------------------------
            */

            DB::table('quiz_attempts')
                ->where('quiz_id', $quiz->id)
                ->delete();


            /*
            |--------------------------------------------------------------------------
            | Delete options
            |--------------------------------------------------------------------------
            |
            | Explicit deletion keeps the process clear and
            | independent of cascade configuration.
            |
            */

            $questionIds = $questions
                ->pluck('id');

            if ($questionIds->isNotEmpty()) {
                DB::table('quiz_options')
                    ->whereIn(
                        'quiz_question_id',
                        $questionIds
                    )
                    ->delete();
            }


            /*
            |--------------------------------------------------------------------------
            | Delete questions
            |--------------------------------------------------------------------------
            */

            DB::table('quiz_questions')
                ->where('quiz_id', $quiz->id)
                ->delete();


            /*
            |--------------------------------------------------------------------------
            | Delete quiz
            |--------------------------------------------------------------------------
            */

            $quiz->delete();
        }, 3);
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE QUESTIONS
    |--------------------------------------------------------------------------
    */

    private function createQuestions(
        Quiz $quiz,
        array $questions
    ): void {
        foreach (
            $questions as $questionIndex => $questionData
        ) {
            $questionImage = null;


            /*
            |--------------------------------------------------------------------------
            | Upload Question Image
            |--------------------------------------------------------------------------
            */

            if (
                isset($questionData['question_image']) &&
                $questionData['question_image']
                    instanceof UploadedFile
            ) {
                $questionImage =
                    $questionData['question_image']
                        ->store(
                            'quiz/questions',
                            'public'
                        );
            }


            /*
            |--------------------------------------------------------------------------
            | Create Question
            |--------------------------------------------------------------------------
            */

            $question =
                $quiz->questions()->create([
                    'question_text' =>
                        $questionData['question_text'],

                    'question_image' =>
                        $questionImage,

                    'type' =>
                        $questionData['type'],

                    'marks' =>
                        $questionData['marks'],

                    'explanation' =>
                        $questionData['explanation']
                        ?? null,

                    'sort_order' =>
                        $questionIndex,
                ]);


            /*
            |--------------------------------------------------------------------------
            | Create Options
            |--------------------------------------------------------------------------
            */

            foreach (
                $questionData['options']
                as $optionIndex => $optionData
            ) {
                $question->options()->create([
                    'option_text' =>
                        $optionData['option_text'],

                    'is_correct' =>
                        (bool) $optionData['is_correct'],

                    'sort_order' =>
                        $optionIndex,
                ]);
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE QUESTIONS
    |--------------------------------------------------------------------------
    */

    private function updateQuestions(
        Quiz $quiz,
        array $questions
    ): void {
        $existingQuestions = $quiz->questions()
            ->with('options')
            ->get()
            ->keyBy('id');


        $processedQuestionIds = [];


        foreach (
            $questions as $questionIndex => $questionData
        ) {
            $question = null;


            /*
            |--------------------------------------------------------------------------
            | 1. Find by question ID if frontend sends it
            |--------------------------------------------------------------------------
            */

            if (
                !empty($questionData['id'])
            ) {
                $question = $existingQuestions
                    ->get(
                        (int) $questionData['id']
                    );

                /*
                |--------------------------------------------------------------------------
                | Security check
                |--------------------------------------------------------------------------
                |
                | The question ID must belong to this quiz.
                |
                */

                if (!$question) {
                    throw ValidationException::withMessages([
                        "questions.{$questionIndex}.id" => [
                            'The selected question does not belong to this quiz.',
                        ],
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | 2. Backward-compatible fallback
            |--------------------------------------------------------------------------
            |
            | If the existing frontend does not yet send IDs,
            | use sort_order.
            |
            */

            if (!$question) {
                $question = $existingQuestions
                    ->first(
                        fn ($existingQuestion) =>
                            $existingQuestion->sort_order
                            === $questionIndex
                    );
            }


            /*
            |--------------------------------------------------------------------------
            | New Question
            |--------------------------------------------------------------------------
            */

            if (!$question) {
                $question =
                    $this->createSingleQuestion(
                        $quiz,
                        $questionData,
                        $questionIndex
                    );
            } else {
                /*
                |--------------------------------------------------------------------------
                | Existing Question
                |--------------------------------------------------------------------------
                */

                $this->updateSingleQuestion(
                    $question,
                    $questionData,
                    $questionIndex
                );
            }


            $processedQuestionIds[] =
                $question->id;


            /*
            |--------------------------------------------------------------------------
            | Update / Create Options
            |--------------------------------------------------------------------------
            */

            $this->updateQuestionOptions(
                $question,
                $questionData['options'],
                $questionIndex
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Remove Questions that were removed from the form
        |--------------------------------------------------------------------------
        |
        | If a question already has student answers,
        | we MUST preserve it.
        |
        | If it has never been answered, it can safely
        | be physically deleted.
        |
        */

        foreach (
            $existingQuestions as $existingQuestion
        ) {
            if (
                in_array(
                    $existingQuestion->id,
                    $processedQuestionIds,
                    true
                )
            ) {
                continue;
            }


            $hasAnswers =
                $existingQuestion->answers()->exists();


            if ($hasAnswers) {
                throw ValidationException::withMessages([
                    'questions' => [
                        'A question that has already been answered by a student cannot be removed from the quiz. You can still edit the question.',
                    ],
                ]);
            }


            $this->deleteQuestion(
                $existingQuestion
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE SINGLE QUESTION
    |--------------------------------------------------------------------------
    */

    private function createSingleQuestion(
        Quiz $quiz,
        array $questionData,
        int $questionIndex
    ): QuizQuestion {
        $questionImage = null;


        if (
            isset($questionData['question_image']) &&
            $questionData['question_image']
                instanceof UploadedFile
        ) {
            $questionImage =
                $questionData['question_image']
                    ->store(
                        'quiz/questions',
                        'public'
                    );
        }


        return $quiz->questions()->create([
            'question_text' =>
                $questionData['question_text'],

            'question_image' =>
                $questionImage,

            'type' =>
                $questionData['type'],

            'marks' =>
                $questionData['marks'],

            'explanation' =>
                $questionData['explanation']
                ?? null,

            'sort_order' =>
                $questionIndex,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE SINGLE QUESTION
    |--------------------------------------------------------------------------
    */

    private function updateSingleQuestion(
        QuizQuestion $question,
        array $questionData,
        int $questionIndex
    ): void {
        $updateData = [
            'question_text' =>
                $questionData['question_text'],

            'type' =>
                $questionData['type'],

            'marks' =>
                $questionData['marks'],

            'explanation' =>
                $questionData['explanation']
                ?? null,

            'sort_order' =>
                $questionIndex,
        ];


        /*
        |--------------------------------------------------------------------------
        | New image uploaded
        |--------------------------------------------------------------------------
        */

        if (
            isset($questionData['question_image']) &&
            $questionData['question_image']
                instanceof UploadedFile
        ) {
            /*
            |--------------------------------------------------------------------------
            | Delete old image
            |--------------------------------------------------------------------------
            */

            if (
                $question->question_image
            ) {
                Storage::disk('public')->delete(
                    $question->question_image
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Store new image
            |--------------------------------------------------------------------------
            */

            $updateData['question_image'] =
                $questionData['question_image']
                    ->store(
                        'quiz/questions',
                        'public'
                    );
        }


        /*
        |--------------------------------------------------------------------------
        | If no new image is uploaded,
        | keep the existing image.
        |--------------------------------------------------------------------------
        */

        $question->update(
            $updateData
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE QUESTION OPTIONS
    |--------------------------------------------------------------------------
    */

    private function updateQuestionOptions(
        QuizQuestion $question,
        array $options,
        int $questionIndex
    ): void {
        $existingOptions = $question->options()
            ->get()
            ->keyBy('id');


        $processedOptionIds = [];


        foreach (
            $options as $optionIndex => $optionData
        ) {
            $option = null;


            /*
            |--------------------------------------------------------------------------
            | Find by option ID
            |--------------------------------------------------------------------------
            */

            if (
                !empty($optionData['id'])
            ) {
                $option = $existingOptions
                    ->get(
                        (int) $optionData['id']
                    );

                if (!$option) {
                    throw ValidationException::withMessages([
                        "questions.{$questionIndex}.options.{$optionIndex}.id" => [
                            'The selected option does not belong to this question.',
                        ],
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Backward-compatible fallback by sort order
            |--------------------------------------------------------------------------
            */

            if (!$option) {
                $option = $existingOptions
                    ->first(
                        fn ($existingOption) =>
                            $existingOption->sort_order
                            === $optionIndex
                    );
            }


            /*
            |--------------------------------------------------------------------------
            | Create new option
            |--------------------------------------------------------------------------
            */

            if (!$option) {
                $option =
                    $question->options()->create([
                        'option_text' =>
                            $optionData['option_text'],

                        'is_correct' =>
                            (bool) $optionData['is_correct'],

                        'sort_order' =>
                            $optionIndex,
                    ]);
            } else {
                /*
                |--------------------------------------------------------------------------
                | Update existing option
                |--------------------------------------------------------------------------
                */

                $option->update([
                    'option_text' =>
                        $optionData['option_text'],

                    'is_correct' =>
                        (bool) $optionData['is_correct'],

                    'sort_order' =>
                        $optionIndex,
                ]);
            }


            $processedOptionIds[] =
                $option->id;
        }


        /*
        |--------------------------------------------------------------------------
        | Delete removed options
        |--------------------------------------------------------------------------
        |
        | We can physically remove an option only when the
        | question has never been answered.
        |
        */

        $hasAnswers =
            $question->answers()->exists();


        foreach (
            $existingOptions as $existingOption
        ) {
            if (
                in_array(
                    $existingOption->id,
                    $processedOptionIds,
                    true
                )
            ) {
                continue;
            }


            if ($hasAnswers) {
                throw ValidationException::withMessages([
                    "questions.{$questionIndex}.options" => [
                        'An option from a question that has already been answered cannot be removed. You can still edit the option.',
                    ],
                ]);
            }


            $existingOption->delete();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE SINGLE QUESTION
    |--------------------------------------------------------------------------
    */

    private function deleteQuestion(
        QuizQuestion $question
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Delete image
        |--------------------------------------------------------------------------
        */

        if (
            $question->question_image
        ) {
            Storage::disk('public')->delete(
                $question->question_image
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Delete options
        |--------------------------------------------------------------------------
        */

        $question->options()->delete();


        /*
        |--------------------------------------------------------------------------
        | Delete question
        |--------------------------------------------------------------------------
        */

        $question->delete();
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE UNIQUE SLUG
    |--------------------------------------------------------------------------
    */

    private function generateUniqueSlug(
        string $title,
        ?int $ignoreId = null
    ): string {
        $slug = Str::slug($title);

        if ($slug === '') {
            $slug = 'quiz';
        }


        $originalSlug = $slug;

        $counter = 2;


        while (
            Quiz::query()
                ->where('slug', $slug)
                ->when(
                    $ignoreId !== null,
                    fn ($query) =>
                        $query->where(
                            'id',
                            '!=',
                            $ignoreId
                        )
                )
                ->exists()
        ) {
            $slug =
                $originalSlug .
                '-' .
                $counter;

            $counter++;
        }


        return $slug;
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD ADMIN QUIZ
    |--------------------------------------------------------------------------
    */

    private function loadAdminQuiz(
        Quiz $quiz
    ): Quiz {
        return $quiz->load([
            'batch.course',
            'creator:id,name,email',
            'questions.options',
        ])->loadCount('questions');
    }
}