<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizResultController extends Controller
{
    /**
     * Show quiz results belonging to accessible courses.
     */
    public function index(Request $request): JsonResponse
    {
        $attempts = $this->visibleAttempts($request->user())
            ->with([
                'user:id,name,email',
                'quiz:id,batch_id,title,total_marks,passing_marks,attempts_allowed',
            ])
            ->whereIn('status', ['submitted', 'expired'])
            ->latest('submitted_at')
            ->get();

        $data = $attempts->map(function (QuizAttempt $attempt) {
            $usedAttempts = QuizAttempt::query()
                ->where('quiz_id', $attempt->quiz_id)
                ->where('user_id', $attempt->user_id)
                ->whereIn('status', [
                    'submitted',
                    'expired',
                    'in_progress',
                ])
                ->count();

            $attemptsAllowed = (int) $attempt->quiz->attempts_allowed;

            return [
                'id' => $attempt->id,

                'student' => [
                    'id' => $attempt->user->id,
                    'name' => $attempt->user->name,
                    'email' => $attempt->user->email,
                ],

                'quiz' => [
                    'id' => $attempt->quiz->id,
                    'title' => $attempt->quiz->title,
                ],

                'attempt_number' => $this->getAttemptNumber($attempt),

                'score' => (float) $attempt->score,
                'total_marks' => (int) $attempt->quiz->total_marks,

                'correct_answers' => (int) $attempt->correct_answers,
                'wrong_answers' => (int) $attempt->wrong_answers,
                'unanswered' => (int) $attempt->unanswered,

                'attempts_allowed' => $attemptsAllowed,
                'used_attempts' => $usedAttempts,

                'left_attempts' => max(
                    $attemptsAllowed - $usedAttempts,
                    0
                ),

                'passed' =>
                    (float) $attempt->score >=
                    (int) $attempt->quiz->passing_marks,

                'status' => $attempt->status,

                'started_at' => $attempt->started_at,
                'submitted_at' => $attempt->submitted_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Show complete result for one attempt.
     */
    public function show(
        Request $request,
        QuizAttempt $attempt
    ): JsonResponse {
        // Check ownership before loading or returning student information.
        $attempt = $this->visibleAttempts($request->user())
            ->findOrFail($attempt->id);

        $attempt->load([
            'user:id,name,email',
            'quiz:id,batch_id,title,total_marks,passing_marks,attempts_allowed',
            'answers.question.options',
        ]);

        if (!in_array($attempt->status, ['submitted', 'expired'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This quiz attempt has not been submitted yet.',
            ], 409);
        }

        $usedAttempts = QuizAttempt::query()
            ->where('quiz_id', $attempt->quiz_id)
            ->where('user_id', $attempt->user_id)
            ->whereIn('status', [
                'submitted',
                'expired',
                'in_progress',
            ])
            ->count();

        $attemptsAllowed = (int) $attempt->quiz->attempts_allowed;

        $questions = $attempt->quiz->questions()
            ->with('options')
            ->orderBy('sort_order')
            ->get();

        $answersByQuestion = $attempt->answers
            ->keyBy('quiz_question_id');

        $questionResults = $questions->map(function ($question) use (
            $answersByQuestion
        ) {
            $answer = $answersByQuestion->get($question->id);

            $selectedOptionIds = $answer
                ? ($answer->selected_option_ids ?? [])
                : [];

            $selectedOptions = $question->options
                ->whereIn('id', $selectedOptionIds)
                ->values()
                ->map(fn ($option) => [
                    'id' => $option->id,
                    'option_text' => $option->option_text,
                ]);

            $correctOptions = $question->options
                ->where('is_correct', true)
                ->values()
                ->map(fn ($option) => [
                    'id' => $option->id,
                    'option_text' => $option->option_text,
                ]);

            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'type' => $question->type,
                'marks' => (int) $question->marks,

                'selected_options' => $selectedOptions,
                'correct_options' => $correctOptions,

                'is_correct' => $answer
                    ? (bool) $answer->is_correct
                    : false,

                'marks_awarded' => $answer
                    ? (float) $answer->marks_awarded
                    : 0,

                'answered_at' => $answer?->answered_at,
            ];
        });

        return response()->json([
            'success' => true,

            'data' => [
                'id' => $attempt->id,

                'student' => [
                    'id' => $attempt->user->id,
                    'name' => $attempt->user->name,
                    'email' => $attempt->user->email,
                ],

                'quiz' => [
                    'id' => $attempt->quiz->id,
                    'title' => $attempt->quiz->title,
                ],

                'attempt_number' => $this->getAttemptNumber($attempt),

                'score' => (float) $attempt->score,

                'total_marks' => (int) $attempt->quiz->total_marks,

                'passing_marks' => (int) $attempt->quiz->passing_marks,

                'correct_answers' => (int) $attempt->correct_answers,

                'wrong_answers' => (int) $attempt->wrong_answers,

                'unanswered' => (int) $attempt->unanswered,

                'attempts_allowed' => $attemptsAllowed,

                'used_attempts' => $usedAttempts,

                'left_attempts' => max(
                    $attemptsAllowed - $usedAttempts,
                    0
                ),

                'passed' =>
                    (float) $attempt->score >=
                    (int) $attempt->quiz->passing_marks,

                'status' => $attempt->status,

                'started_at' => $attempt->started_at,

                'submitted_at' => $attempt->submitted_at,

                'questions' => $questionResults,
            ],
        ]);
    }

    /**
     * Main Admin sees all attempts.
     * Teacher sees attempts for quizzes in their own courses.
     */
    // private function visibleAttempts(User $user): Builder
    // {
    //     return QuizAttempt::query()->whereHas(
    //         'quiz.batch.course',
    //         function ($query) use ($user) {
    //             if (!$user->isAdmin()) {
    //                 $query->where('owner_id', $user->id);
    //             }
    //         }
    //     );
    // }


private function visibleAttempts(User $user): Builder
{
    return QuizAttempt::query()->whereHas(
        'quiz',
        function ($quizQuery) use ($user) {
            $quizQuery->where(function ($placementQuery) use ($user) {

                // Course-based quizzes without batches.
                $placementQuery->whereHas(
                    'course',
                    function ($courseQuery) use ($user) {
                        if (!$user->isAdmin()) {
                            $courseQuery->where('owner_id', $user->id);
                        }
                    }
                );

                // Existing batch-based quizzes.
                $placementQuery->orWhereHas(
                    'batch.course',
                    function ($courseQuery) use ($user) {
                        if (!$user->isAdmin()) {
                            $courseQuery->where('owner_id', $user->id);
                        }
                    }
                );
            });
        }
    );
}


    /**
     * Calculate this student's attempt number for this quiz.
     */
    private function getAttemptNumber(QuizAttempt $attempt): int
    {
        return QuizAttempt::query()
            ->where('quiz_id', $attempt->quiz_id)
            ->where('user_id', $attempt->user_id)
            ->where('id', '<=', $attempt->id)
            ->count();
    }
}