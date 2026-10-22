<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuizAttemptService
{
    /*
    |--------------------------------------------------------------------------
    | START QUIZ
    |--------------------------------------------------------------------------
    */

    public function start(
        Quiz $quiz,
        int $userId
    ): array {
        return DB::transaction(function () use (
            $quiz,
            $userId
        ) {
            /*
            |--------------------------------------------------------------------------
            | Lock quiz row
            |--------------------------------------------------------------------------
            |
            | Prevent two simultaneous start requests from creating
            | multiple attempts incorrectly.
            |
            */

            $quiz = Quiz::query()
                ->lockForUpdate()
                ->findOrFail($quiz->id);

            if ($quiz->status !== 'published') {
                $this->fail(
                    'This quiz is not available.',
                    409
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Check existing active attempt
            |--------------------------------------------------------------------------
            |
            | If the user refreshes the browser, do not create another attempt.
            | Resume the existing attempt instead.
            |
            */

            $activeAttempt = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $userId)
                ->where('status', 'in_progress')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($activeAttempt) {
                if (
                    $activeAttempt->expires_at &&
                    now()->greaterThanOrEqualTo(
                        $activeAttempt->expires_at
                    )
                ) {
                    $this->finalizeAttempt(
                        $activeAttempt,
                        'expired'
                    );
                } else {
                    return $this->buildAttemptResponse(
                        $activeAttempt->fresh()
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Count consumed attempts
            |--------------------------------------------------------------------------
            |
            | Submitted and expired attempts consume an attempt.
            |
            */

            $usedAttempts = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $userId)
                ->whereIn('status', [
                    'submitted',
                    'expired',
                ])
                ->count();

            if (
                $usedAttempts >=
                (int) $quiz->attempts_allowed
            ) {
                $this->fail(
                    'You have no attempts remaining.',
                    409
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Create new attempt
            |--------------------------------------------------------------------------
            */

            $startedAt = now();

            $expiresAt = $startedAt->copy()->addMinutes(
                (int) $quiz->duration_minutes
            );

            $attempt = QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'user_id' => $userId,

                'started_at' => $startedAt,
                'expires_at' => $expiresAt,

                'submitted_at' => null,

                'status' => 'in_progress',

                'score' => null,

                'correct_answers' => 0,
                'wrong_answers' => 0,
                'unanswered' => 0,
            ]);

            return $this->buildAttemptResponse(
                $attempt
            );
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | GET ATTEMPT
    |--------------------------------------------------------------------------
    */

    public function show(
        QuizAttempt $attempt,
        int $userId
    ): array {
        $this->authorizeAttempt(
            $attempt,
            $userId
        );

        return DB::transaction(function () use (
            $attempt
        ) {
            $attempt = QuizAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            /*
            |--------------------------------------------------------------------------
            | Automatically expire an old attempt
            |--------------------------------------------------------------------------
            */

            if (
                $attempt->status === 'in_progress' &&
                $attempt->expires_at &&
                now()->greaterThanOrEqualTo(
                    $attempt->expires_at
                )
            ) {
                $this->finalizeAttempt(
                    $attempt,
                    'expired'
                );

                $attempt->refresh();
            }

            return $this->buildAttemptResponse(
                $attempt
            );
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE ANSWER
    |--------------------------------------------------------------------------
    */

    public function saveAnswer(
        QuizAttempt $attempt,
        int $userId,
        array $data
    ): array {
        $this->authorizeAttempt(
            $attempt,
            $userId
        );

        return DB::transaction(function () use (
            $attempt,
            $data
        ) {
            /*
            |--------------------------------------------------------------------------
            | Lock attempt
            |--------------------------------------------------------------------------
            */

            $attempt = QuizAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            /*
            |--------------------------------------------------------------------------
            | Attempt must still be active
            |--------------------------------------------------------------------------
            */

            if ($attempt->status !== 'in_progress') {
                $this->fail(
                    'This quiz attempt is no longer active.',
                    409
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Timer check
            |--------------------------------------------------------------------------
            */

            if (
                $attempt->expires_at &&
                now()->greaterThanOrEqualTo(
                    $attempt->expires_at
                )
            ) {
                $this->finalizeAttempt(
                    $attempt,
                    'expired'
                );

                $this->fail(
                    'The quiz time has expired.',
                    409
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Find question
            |--------------------------------------------------------------------------
            |
            | Very important:
            |
            | The question must belong to THIS quiz.
            |
            | This prevents a user from sending a question ID
            | belonging to another quiz.
            |
            */

            $question = QuizQuestion::query()
                ->with('options')
                ->where('id', $data['quiz_question_id'])
                ->where(
                    'quiz_id',
                    $attempt->quiz_id
                )
                ->first();

            if (!$question) {
                $this->fail(
                    'This question does not belong to this quiz.',
                    422
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Normalize selected option IDs
            |--------------------------------------------------------------------------
            */

            $selectedOptionIds = collect(
                $data['selected_option_ids'] ?? []
            )
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            /*
            |--------------------------------------------------------------------------
            | Get valid options for this question
            |--------------------------------------------------------------------------
            */

            $validOptionIds = $question
                ->options
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            /*
            |--------------------------------------------------------------------------
            | Prevent selecting options belonging
            | to another question
            |--------------------------------------------------------------------------
            */

            $invalidOptionIds = array_diff(
                $selectedOptionIds,
                $validOptionIds
            );

            if (!empty($invalidOptionIds)) {
                $this->fail(
                    'One or more selected options do not belong to this question.',
                    422
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Single select
            |--------------------------------------------------------------------------
            */

            if (
                $question->type === 'single_select' &&
                count($selectedOptionIds) > 1
            ) {
                $this->fail(
                    'Only one option can be selected for this question.',
                    422
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Save answer
            |--------------------------------------------------------------------------
            |
            | We do NOT calculate score here.
            |
            | Score is calculated only by the server during submit.
            |
            */

            $answer = QuizAnswer::updateOrCreate(
                [
                    'attempt_id' => $attempt->id,
                    'quiz_question_id' => $question->id,
                ],
                [
                    'selected_option_ids' =>
                        $selectedOptionIds,

                    'is_correct' => null,

                    'marks_awarded' => 0,

                    'answered_at' =>
                        count($selectedOptionIds) > 0
                            ? now()
                            : null,
                ]
            );

            return [
                'id' => $answer->id,

                'attempt_id' =>
                    $answer->attempt_id,

                'quiz_question_id' =>
                    $answer->quiz_question_id,

                'selected_option_ids' =>
                    $selectedOptionIds,

                'saved_at' =>
                    $answer->updated_at,
            ];
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT QUIZ
    |--------------------------------------------------------------------------
    */

    public function submit(
        QuizAttempt $attempt,
        int $userId
    ): array {
        $this->authorizeAttempt(
            $attempt,
            $userId
        );

        return DB::transaction(function () use (
            $attempt
        ) {
            $attempt = QuizAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            /*
            |--------------------------------------------------------------------------
            | Double submit protection
            |--------------------------------------------------------------------------
            */

            if (
                $attempt->status === 'submitted' ||
                $attempt->status === 'expired'
            ) {
                return $this->buildSubmittedResponse(
                    $attempt
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Timer expired
            |--------------------------------------------------------------------------
            */

            if (
                $attempt->expires_at &&
                now()->greaterThanOrEqualTo(
                    $attempt->expires_at
                )
            ) {
                $this->finalizeAttempt(
                    $attempt,
                    'expired'
                );

                return $this->buildSubmittedResponse(
                    $attempt->fresh()
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Normal submission
            |--------------------------------------------------------------------------
            */

            $this->finalizeAttempt(
                $attempt,
                'submitted'
            );

            return $this->buildSubmittedResponse(
                $attempt->fresh()
            );
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | RESULT
    |--------------------------------------------------------------------------
    */

    public function result(
        QuizAttempt $attempt,
        int $userId
    ): array {
        $this->authorizeAttempt(
            $attempt,
            $userId
        );

        return DB::transaction(function () use (
            $attempt
        ) {
            $attempt = QuizAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            /*
            |--------------------------------------------------------------------------
            | If time expired before result request
            |--------------------------------------------------------------------------
            */

            if (
                $attempt->status === 'in_progress' &&
                $attempt->expires_at &&
                now()->greaterThanOrEqualTo(
                    $attempt->expires_at
                )
            ) {
                $this->finalizeAttempt(
                    $attempt,
                    'expired'
                );

                $attempt->refresh();
            }

            /*
            |--------------------------------------------------------------------------
            | Result is available only after submit/expiry
            |--------------------------------------------------------------------------
            */

            if (
                $attempt->status === 'in_progress'
            ) {
                $this->fail(
                    'The quiz has not been submitted yet.',
                    409
                );
            }

            $quiz = $attempt->quiz()
                ->with([
                    'questions.options',
                ])
                ->firstOrFail();

            /*
            |--------------------------------------------------------------------------
            | Result visibility
            |--------------------------------------------------------------------------
            */

            if (!$quiz->show_result) {
                $this->fail(
                    'The quiz result is not available yet.',
                    403
                );
            }

            $answers = $attempt
                ->answers()
                ->get()
                ->keyBy('quiz_question_id');

            $questions = $this->orderedQuestions(
                $quiz,
                $attempt
            );

            $questionResults = [];

            foreach ($questions as $question) {
                $answer =
                    $answers->get($question->id);

                $selectedOptionIds =
                    $this->normalizeIds(
                        $answer?->selected_option_ids ?? []
                    );

                $item = [
                    'id' => $question->id,

                    'question_text' =>
                        $question->question_text,

                    'type' =>
                        $question->type,

                    'marks' =>
                        (int) $question->marks,

                    'selected_option_ids' =>
                        $selectedOptionIds,

                    'is_correct' =>
                        $answer?->is_correct,

                    'marks_awarded' =>
                        round(
                            (float) (
                                $answer?->marks_awarded ?? 0
                            ),
                            2
                        ),
                ];

                /*
                |--------------------------------------------------------------------------
                | Correct answers are ONLY returned
                | when the quiz allows them.
                |--------------------------------------------------------------------------
                */

                if (
                    $quiz->show_correct_answers
                ) {
                    $item['correct_option_ids'] =
                        $question
                            ->options
                            ->filter(
                                fn ($option) =>
                                    $option->is_correct
                            )
                            ->pluck('id')
                            ->map(
                                fn ($id) => (int) $id
                            )
                            ->sort()
                            ->values()
                            ->all();

                    $item['explanation'] =
                        $question->explanation;
                }

                $questionResults[] = $item;
            }

            $score = round(
                (float) $attempt->score,
                2
            );

            $passingMarks =
                (int) $quiz->passing_marks;

            return [
                'id' => $attempt->id,

                'quiz_id' =>
                    $attempt->quiz_id,

                'quiz_title' =>
                    $quiz->title,

                'status' =>
                    $attempt->status,

                'score' =>
                    $score,

                'total_marks' =>
                    (int) $quiz->total_marks,

                'passing_marks' =>
                    $passingMarks,

                'passed' =>
                    $score >= $passingMarks,

                'correct_answers' =>
                    (int) $attempt->correct_answers,

                'wrong_answers' =>
                    (int) $attempt->wrong_answers,

                'unanswered' =>
                    (int) $attempt->unanswered,

                'started_at' =>
                    $attempt->started_at,

                'submitted_at' =>
                    $attempt->submitted_at,

                'questions' =>
                    $questionResults,
            ];
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | FINALIZE ATTEMPT
    |--------------------------------------------------------------------------
    */

    private function finalizeAttempt(
        QuizAttempt $attempt,
        string $status
    ): void {
        $quiz = $attempt
            ->quiz()
            ->with([
                'questions.options',
            ])
            ->firstOrFail();

        $answers = $attempt
            ->answers()
            ->get()
            ->keyBy('quiz_question_id');

        $score = 0;

        $correctAnswers = 0;

        $wrongAnswers = 0;

        $unanswered = 0;

        foreach ($quiz->questions as $question) {
            $answer =
                $answers->get($question->id);

            $selectedOptionIds =
                $this->normalizeIds(
                    $answer?->selected_option_ids ?? []
                );

            $correctOptionIds =
                $question
                    ->options
                    ->filter(
                        fn ($option) =>
                            $option->is_correct
                    )
                    ->pluck('id')
                    ->map(
                        fn ($id) => (int) $id
                    )
                    ->sort()
                    ->values()
                    ->all();

            /*
            |--------------------------------------------------------------------------
            | Empty selection
            |--------------------------------------------------------------------------
            */

            if (empty($selectedOptionIds)) {
                $isCorrect = null;

                $marksAwarded = 0;

                $unanswered++;
            } else {
                /*
                |--------------------------------------------------------------------------
                | Exact-match grading
                |--------------------------------------------------------------------------
                |
                | Example:
                |
                | Correct: [2, 4]
                | User:    [2, 4]  -> CORRECT
                |
                | Correct: [2, 4]
                | User:    [2]     -> WRONG
                |
                | Correct: [2, 4]
                | User:    [2, 3, 4] -> WRONG
                |
                */

                $isCorrect =
                    $selectedOptionIds ===
                    $correctOptionIds;

                if ($isCorrect) {
                    $marksAwarded =
                        (int) $question->marks;

                    $score += $marksAwarded;

                    $correctAnswers++;
                } else {
                    $marksAwarded = 0;

                    $wrongAnswers++;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Every question gets an answer row
            |--------------------------------------------------------------------------
            |
            | This makes reporting much easier.
            |
            */

            QuizAnswer::updateOrCreate(
                [
                    'attempt_id' =>
                        $attempt->id,

                    'quiz_question_id' =>
                        $question->id,
                ],
                [
                    'selected_option_ids' =>
                        $selectedOptionIds,

                    'is_correct' =>
                        $isCorrect,

                    'marks_awarded' =>
                        $marksAwarded,

                    'answered_at' =>
                        $answer?->answered_at,
                ]
            );
        }

        $attempt->update([
            'status' => $status,

            'score' => $score,

            'correct_answers' =>
                $correctAnswers,

            'wrong_answers' =>
                $wrongAnswers,

            'unanswered' =>
                $unanswered,

            'submitted_at' =>
                now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ATTEMPT RESPONSE
    |--------------------------------------------------------------------------
    */

    private function buildAttemptResponse(
        QuizAttempt $attempt
    ): array {
        $attempt->load([
            'quiz.questions.options',
        ]);

        $quiz = $attempt->quiz;

        $answers = $attempt
            ->answers()
            ->get()
            ->keyBy('quiz_question_id');

        $questions = $this->orderedQuestions(
            $quiz,
            $attempt
        );

        $questionData = [];

        foreach ($questions as $question) {
            $answer =
                $answers->get($question->id);

            $options = $question->options;

            /*
            |--------------------------------------------------------------------------
            | Deterministic option shuffle
            |--------------------------------------------------------------------------
            |
            | Same attempt always gets the same order.
            |
            | We do not need another database table.
            |
            */

            if ($quiz->shuffle_options) {
                $options = $options
                    ->sortBy(
                        fn ($option) =>
                            hash(
                                'sha256',
                                $attempt->id .
                                '|option|' .
                                $option->id
                            )
                    )
                    ->values();
            }

            $questionData[] = [
                'id' =>
                    $question->id,

                'question_text' =>
                    $question->question_text,

                'type' =>
                    $question->type,

                'marks' =>
                    (int) $question->marks,

                'sort_order' =>
                    (int) $question->sort_order,

                /*
                |--------------------------------------------------------------------------
                | IMPORTANT SECURITY RULE
                |--------------------------------------------------------------------------
                |
                | is_correct is intentionally NOT returned.
                |
                */

                'options' =>
                    $options->map(
                        fn ($option) => [
                            'id' =>
                                $option->id,

                            'option_text' =>
                                $option->option_text,

                            'sort_order' =>
                                (int) $option->sort_order,
                        ]
                    )->values()->all(),

                'selected_option_ids' =>
                    $this->normalizeIds(
                        $answer?->selected_option_ids ?? []
                    ),
            ];
        }

        $remainingSeconds = 0;

        if ($attempt->expires_at) {
            $remainingSeconds = max(
                0,
                $attempt->expires_at
                    ->getTimestamp() -
                now()->getTimestamp()
            );
        }

        return [
            'id' =>
                $attempt->id,

            'quiz_id' =>
                $attempt->quiz_id,

            'status' =>
                $attempt->status,

            'started_at' =>
                $attempt->started_at,

            'expires_at' =>
                $attempt->expires_at,

            'remaining_seconds' =>
                $remainingSeconds,

            'quiz' => [
                'id' =>
                    $quiz->id,

                'title' =>
                    $quiz->title,

                'duration_minutes' =>
                    (int) $quiz->duration_minutes,

                'total_marks' =>
                    (int) $quiz->total_marks,

                'passing_marks' =>
                    (int) $quiz->passing_marks,

                'auto_submit' =>
                    (bool) $quiz->auto_submit,

                'show_result' =>
                    (bool) $quiz->show_result,
            ],

            'questions' =>
                $questionData,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMITTED RESPONSE
    |--------------------------------------------------------------------------
    */

    private function buildSubmittedResponse(
        QuizAttempt $attempt
    ): array {
        $attempt->load('quiz');

        return [
            'id' =>
                $attempt->id,

            'quiz_id' =>
                $attempt->quiz_id,

            'status' =>
                $attempt->status,

            'score' =>
                round(
                    (float) $attempt->score,
                    2
                ),

            'total_marks' =>
                (int) $attempt
                    ->quiz
                    ->total_marks,

            'passing_marks' =>
                (int) $attempt
                    ->quiz
                    ->passing_marks,

            'passed' =>
                (float) $attempt->score >=
                (int) $attempt
                    ->quiz
                    ->passing_marks,

            'correct_answers' =>
                (int) $attempt->correct_answers,

            'wrong_answers' =>
                (int) $attempt->wrong_answers,

            'unanswered' =>
                (int) $attempt->unanswered,

            'submitted_at' =>
                $attempt->submitted_at,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER QUESTIONS
    |--------------------------------------------------------------------------
    */

    private function orderedQuestions(
        Quiz $quiz,
        QuizAttempt $attempt
    ) {
        $questions = $quiz->questions;

        if (!$quiz->shuffle_questions) {
            return $questions->values();
        }

        return $questions
            ->sortBy(
                fn ($question) =>
                    hash(
                        'sha256',
                        $attempt->id .
                        '|question|' .
                        $question->id
                    )
            )
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE IDS
    |--------------------------------------------------------------------------
    */

    private function normalizeIds(
        mixed $ids
    ): array {
        if (!is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | AUTHORIZATION
    |--------------------------------------------------------------------------
    */

    private function authorizeAttempt(
        QuizAttempt $attempt,
        int $userId
    ): void {
        if ($attempt->user_id !== $userId) {
            $this->fail(
                'You are not allowed to access this quiz attempt.',
                403
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ERROR HELPER
    |--------------------------------------------------------------------------
    */

    private function fail(
        string $message,
        int $status
    ): never {
        throw new HttpException(
            $status,
            $message
        );
    }
}