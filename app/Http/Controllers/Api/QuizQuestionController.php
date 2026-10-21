<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddQuizQuestionsRequest;
use App\Http\Requests\UpdateQuizQuestionRequest;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizQuestionController extends Controller
{
    public function store(
        AddQuizQuestionsRequest $request,
        string $quizId
    ): JsonResponse {

        $quiz = Quiz::findOrFail($quizId);

        $this->authorizeQuizModification($quiz);

        $questionIds = $request->validated()['question_ids'];

        /*
        |--------------------------------------------------------------------------
        | Check duplicate questions
        |--------------------------------------------------------------------------
        */

        $existingQuestionIds = QuizQuestion::where('quiz_id', $quiz->id)
            ->whereIn('question_id', $questionIds)
            ->pluck('question_id')
            ->toArray();

        if (!empty($existingQuestionIds)) {

            return response()->json([
                'success' => false,
                'message' => 'One or more questions are already attached to this quiz.',
                'question_ids' => $existingQuestionIds,
            ], 422);
        }

        DB::transaction(function () use ($quiz, $questionIds) {

            $lastSortOrder = QuizQuestion::where('quiz_id', $quiz->id)
                ->max('sort_order');

            $nextSortOrder = $lastSortOrder === null
                ? 0
                : $lastSortOrder + 1;

            foreach ($questionIds as $questionId) {

                QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question_id' => $questionId,
                    'sort_order' => $nextSortOrder,
                ]);

                $nextSortOrder++;
            }
        });

        $quiz->load([
            'questions.options',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Questions added to quiz successfully.',
            'data' => $quiz,
        ]);
    }

    public function update(
        UpdateQuizQuestionRequest $request,
        string $quizId,
        string $questionId
    ): JsonResponse {

        $quiz = Quiz::findOrFail($quizId);

        $this->authorizeQuizModification($quiz);

        $quizQuestion = QuizQuestion::where('quiz_id', $quiz->id)
            ->where('question_id', $questionId)
            ->firstOrFail();

        $quizQuestion->update(
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz question order updated successfully.',
            'data' => $quizQuestion,
        ]);
    }

    public function updateOrder(
        Request $request,
        string $quizId
    ): JsonResponse {

        $quiz = Quiz::findOrFail($quizId);

        $this->authorizeQuizModification($quiz);

        $request->validate([
            'question_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'question_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:questions,id',
            ],
        ]);

        $questionIds = $request->input('question_ids');

        $quizQuestionIds = QuizQuestion::where('quiz_id', $quiz->id)
            ->pluck('question_id')
            ->sort()
            ->values()
            ->toArray();

        $submittedIds = collect($questionIds)
            ->sort()
            ->values()
            ->toArray();

        if ($quizQuestionIds !== $submittedIds) {

            return response()->json([
                'success' => false,
                'message' => 'The submitted question list does not match the questions currently attached to this quiz.',
            ], 422);
        }

        DB::transaction(function () use ($quiz, $questionIds) {

            foreach ($questionIds as $index => $questionId) {

                QuizQuestion::where('quiz_id', $quiz->id)
                    ->where('question_id', $questionId)
                    ->update([
                        'sort_order' => $index,
                    ]);
            }
        });

        $quiz->load([
            'questions' => function ($query) {
                $query->orderBy('quiz_questions.sort_order');
            },
            'questions.options',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Question order updated successfully.',
            'data' => $quiz,
        ]);
    }

    public function destroy(
        Request $request,
        string $quizId,
        string $questionId
    ): JsonResponse {

        $quiz = Quiz::findOrFail($quizId);

        $this->authorizeQuizModification($quiz);

        $quizQuestion = QuizQuestion::where('quiz_id', $quiz->id)
            ->where('question_id', $questionId)
            ->firstOrFail();

        $quizQuestion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Question removed from quiz successfully.',
        ]);
    }

    private function authorizeQuizModification(Quiz $quiz): void
    {
        abort_unless(
            request()->user()?->role === 'admin',
            403,
            'Admin access required.'
        );

        if ($quiz->attempts()->exists()) {
            abort(
                422,
                'This quiz cannot be modified because students have already attempted it.'
            );
        }
    }
}