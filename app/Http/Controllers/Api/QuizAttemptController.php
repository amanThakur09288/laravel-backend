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
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | START QUIZ
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        Quiz $quiz
    ): JsonResponse {
        $student = $request->user();

        $data = $this->attemptService->start(
            $quiz,
            (int) $student->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz attempt started successfully.',
            'data' => $data,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | GET / RESUME ATTEMPT
    |--------------------------------------------------------------------------
    */

    public function show(
        Request $request,
        QuizAttempt $attempt
    ): JsonResponse {
        $data = $this->attemptService->show(
            $attempt,
            (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE ANSWER
    |--------------------------------------------------------------------------
    */

    public function saveAnswer(
        SaveQuizAnswerRequest $request,
        QuizAttempt $attempt
    ): JsonResponse {
        $data = $this->attemptService->saveAnswer(
            $attempt,
            (int) $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Answer saved successfully.',
            'data' => $data,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT QUIZ
    |--------------------------------------------------------------------------
    */

    public function submit(
        Request $request,
        QuizAttempt $attempt
    ): JsonResponse {
        $data = $this->attemptService->submit(
            $attempt,
            (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz submitted successfully.',
            'data' => $data,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET RESULT
    |--------------------------------------------------------------------------
    */

    public function result(
        Request $request,
        QuizAttempt $attempt
    ): JsonResponse {
        $data = $this->attemptService->result(
            $attempt,
            (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}