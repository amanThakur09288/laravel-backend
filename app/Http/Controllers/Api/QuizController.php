<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizRequest;
use App\Http\Requests\UpdateQuizRequest;
use App\Models\Quiz;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;

class QuizController extends Controller
{
    public function __construct(
        private QuizService $quizService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | List Quizzes
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        $quizzes = Quiz::query()
            ->with([
                'batch.course',
                'creator:id,name,email',
            ])
            ->withCount('questions')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $quizzes,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Quiz
    |--------------------------------------------------------------------------
    */

    public function store(
        StoreQuizRequest $request
    ): JsonResponse {
        $quiz = $this->quizService->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz created successfully.',
            'data' => $quiz,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Quiz
    |--------------------------------------------------------------------------
    */

    public function show(
        Quiz $quiz
    ): JsonResponse {
        $quiz->load([
            'batch.course',
            'creator:id,name,email',
            'questions.options',
        ]);

        return response()->json([
            'success' => true,
            'data' => $quiz,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Quiz
    |--------------------------------------------------------------------------
    */

    public function update(
        UpdateQuizRequest $request,
        Quiz $quiz
    ): JsonResponse {
        $quiz = $this->quizService->update(
            $quiz,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz updated successfully.',
            'data' => $quiz,
        ]);
    }

    public function publicIndex(): JsonResponse
{
    $quizzes = Quiz::query()
        ->with('batch.course')
        ->where('status', 'published')
        ->latest()
        ->get([
            'id',
            'batch_id',
            'title',
            'slug',
            'description',
            'duration_minutes',
            'total_marks',
            'passing_marks',
            'attempts_allowed',
            'status',
            'created_at',
        ]);

    return response()->json([
        'success' => true,
        'data' => $quizzes,
    ]);
}

public function publicShow(string $id): JsonResponse
{
    $quiz = Quiz::query()
        ->with([
            'batch.course',
            'questions' => function ($query) {
                $query->select([
                    'id',
                    'quiz_id',
                    'question_text',
                    'type',
                    'marks',
                    'sort_order',
                ])
                ->with([
                    'options' => function ($query) {
                        $query->select([
                            'id',
                            'quiz_question_id',
                            'option_text',
                            'sort_order',
                        ]);
                    },
                ]);
            },
        ])
        ->where('status', 'published')
        ->findOrFail($id);

    return response()->json([
        'success' => true,
        'data' => $quiz,
    ]);
}

    /*
    |--------------------------------------------------------------------------
    | Delete Quiz
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Quiz $quiz
    ): JsonResponse {
        $this->quizService->delete($quiz);

        return response()->json([
            'success' => true,
            'message' => 'Quiz deleted successfully.',
        ]);
    }
}