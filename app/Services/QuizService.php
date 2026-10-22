<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuizService
{
    /*
    |--------------------------------------------------------------------------
    | Create Quiz
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
    | Update Quiz
    |--------------------------------------------------------------------------
    */

    public function update(
        Quiz $quiz,
        array $data
    ): Quiz {
        return DB::transaction(function () use (
            $quiz,
            $data
        ) {
            /*
            |--------------------------------------------------------------------------
            | Lock quiz during update
            |--------------------------------------------------------------------------
            */

            $quiz = Quiz::query()
                ->whereKey($quiz->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
            |--------------------------------------------------------------------------
            | Do not modify a quiz after attempts exist
            |--------------------------------------------------------------------------
            */

            if ($quiz->attempts()->exists()) {
                throw ValidationException::withMessages([
                    'quiz' => [
                        'This quiz cannot be edited because students have already attempted it.',
                    ],
                ]);
            }

            $questions = $data['questions'];

            $totalMarks = collect($questions)
                ->sum(
                    fn ($question) =>
                        (int) $question['marks']
                );

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
            | Replace questions atomically
            |--------------------------------------------------------------------------
            */

            $quiz->questions()->delete();

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
    | Delete Quiz
    |--------------------------------------------------------------------------
    */

    public function delete(Quiz $quiz): void
    {
        DB::transaction(function () use ($quiz) {
            $quiz = Quiz::query()
                ->whereKey($quiz->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quiz->attempts()->exists()) {
                throw ValidationException::withMessages([
                    'quiz' => [
                        'This quiz cannot be deleted because students have already attempted it.',
                    ],
                ]);
            }

            $quiz->delete();
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Questions
    |--------------------------------------------------------------------------
    */

    private function createQuestions(
        Quiz $quiz,
        array $questions
    ): void {
        foreach (
            $questions as $questionIndex => $questionData
        ) {
            $question = $quiz->questions()->create([
                'question_text' =>
                    $questionData['question_text'],

                'type' =>
                    $questionData['type'],

                'marks' =>
                    $questionData['marks'],

                'explanation' =>
                    $questionData['explanation'] ?? null,

                'sort_order' =>
                    $questionIndex,
            ]);

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
    | Generate Unique Slug
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
    | Admin Response
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