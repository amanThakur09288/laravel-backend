<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizRequest;
use App\Http\Requests\UpdateQuizRequest;
use App\Models\Batch;
use App\Models\Quiz;
use App\Models\User;
use App\Services\QuizService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class QuizController extends Controller
{
    public function __construct(
        private QuizService $quizService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN - LIST QUIZZES
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        $quizzes = $this->visibleQuizzes(request()->user())
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
    | ADMIN - CREATE QUIZ
    |--------------------------------------------------------------------------
    */

    public function store(StoreQuizRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->ensureBatchAccess(
            (int) $data['batch_id'],
            $request->user()
        );

        $quiz = $this->quizService->create(
            $data,
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
    | ADMIN - SHOW QUIZ
    |--------------------------------------------------------------------------
    */

    public function show(Quiz $quiz): JsonResponse
    {
        $quiz = $this->visibleQuizzes(request()->user())
            ->with([
                'batch.course',
                'creator:id,name,email',
                'questions.options',
            ])
            ->withCount('questions')
            ->findOrFail($quiz->id);

        return response()->json([
            'success' => true,
            'data' => $quiz,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN - UPDATE QUIZ
    |--------------------------------------------------------------------------
    */

    public function update(
        UpdateQuizRequest $request,
        Quiz $quiz
    ): JsonResponse {
        $this->visibleQuizzes($request->user())
            ->findOrFail($quiz->id);

        $data = $request->validated();

        // Prevent moving a quiz into another admin's batch.
        $this->ensureBatchAccess(
            (int) $data['batch_id'],
            $request->user()
        );

        $quiz = $this->quizService->update(
            $quiz,
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz updated successfully.',
            'data' => $quiz,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLIC - LIST PUBLISHED QUIZZES
    |--------------------------------------------------------------------------
    */

    public function publicIndex(): JsonResponse
    {
        $quizzes = Quiz::query()
            ->with('batch.course')
            ->withCount('questions')
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

    /*
    |--------------------------------------------------------------------------
    | PUBLIC - SHOW PUBLISHED QUIZ
    |--------------------------------------------------------------------------
    */

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
                    ])->with([
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
    | ADMIN - DELETE QUIZ
    |--------------------------------------------------------------------------
    */

    public function destroy(Quiz $quiz): JsonResponse
    {
        $this->visibleQuizzes(request()->user())
            ->findOrFail($quiz->id);

        $this->quizService->delete($quiz);

        return response()->json([
            'success' => true,
            'message' => 'Quiz deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | OWNERSHIP HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * A quiz belongs to the owner of the course attached to its batch.
     */
    private function visibleQuizzes(User $user): Builder
    {
        return Quiz::query()->whereHas(
            'batch.course',
            function ($query) use ($user) {
                if (!$user->isAdmin()) {
                    $query->where('owner_id', $user->id);
                }
            }
        );
    }

    /**
     * Ensure the selected batch belongs to an accessible course.
     */
    private function ensureBatchAccess(
        int $batchId,
        User $user
    ): void {
        $batch = Batch::with('course')->findOrFail($batchId);

        abort_unless(
            $batch->course && (
                $user->isAdmin() ||
                (int) $batch->course->owner_id === (int) $user->id
            ),
            404,
            'The selected batch was not found.'
        );
    }
}