<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveQuizAnswerRequest;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\QuizAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizAttemptController extends Controller
{
    public function __construct(
        private QuizAttemptService $attemptService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Start Quiz
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        Quiz $quiz
    ): JsonResponse {
        $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $data = $this->attemptService->start(
            $quiz,
            (int) $request->user_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz attempt started successfully.',
            'data' => $data,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Get Attempt
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        QuizAttempt $attempt
    ): JsonResponse {
        $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $data = $this->attemptService->show(
            $attempt,
            (int) $request->user_id
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Save Answer
    |--------------------------------------------------------------------------
    */

    public function saveAnswer(
        Request $request,
        QuizAttempt $attempt
    ): JsonResponse {
        $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'quiz_question_id' => [
                'required',
                'integer',
                'exists:quiz_questions,id',
            ],
            'selected_option_ids' => [
                'present',
                'array',
                'max:20',
            ],
            'selected_option_ids.*' => [
                'integer',
                'distinct',
                'exists:quiz_options,id',
            ],
        ]);

        $data = $this->attemptService->saveAnswer(
            $attempt,
            (int) $request->user_id,
            [
                'quiz_question_id' =>
                    $request->quiz_question_id,

                'selected_option_ids' =>
                    $request->selected_option_ids,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Answer saved successfully.',
            'data' => $data,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Submit Quiz
    |--------------------------------------------------------------------------
    */

    public function submit(
        Request $request,
        QuizAttempt $attempt
    ): JsonResponse {
        $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $data = $this->attemptService->submit(
            $attempt,
            (int) $request->user_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz submitted successfully.',
            'data' => $data,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Result
    |--------------------------------------------------------------------------
    */

    public function result(
        Request $request,
        QuizAttempt $attempt
    ): JsonResponse {
        $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $data = $this->attemptService->result(
            $attempt,
            (int) $request->user_id
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}