<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizRequest;
use App\Http\Requests\UpdateQuizRequest;
use App\Models\Batch;
use App\Models\Course;
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

    public function index(): JsonResponse
    {
        $quizzes = $this->visibleQuizzes(request()->user())
            ->with([
                'batch.course',
                'course',
                'section',
                'lesson',
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

    public function store(StoreQuizRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (!empty($data['batch_id'])) {
            $this->ensureBatchAccess(
                (int) $data['batch_id'],
                $request->user()
            );
        }

        $this->ensureCoursePlacement(
            $data,
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

    public function show(Quiz $quiz): JsonResponse
    {
        $quiz = $this->visibleQuizzes(request()->user())
            ->with([
                'batch.course',
                'course',
                'section',
                'lesson',
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

    public function update(
        UpdateQuizRequest $request,
        Quiz $quiz
    ): JsonResponse {
        $this->visibleQuizzes($request->user())
            ->findOrFail($quiz->id);

        $data = $request->validated();

        if (!empty($data['batch_id'])) {
            $this->ensureBatchAccess(
                (int) $data['batch_id'],
                $request->user()
            );
        }

        $this->ensureCoursePlacement(
            $data,
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

    public function publicIndex(): JsonResponse
    {
        $quizzes = Quiz::query()
            ->with([
                'batch.course',
                'course',
                'section',
                'lesson',
            ])
            ->withCount('questions')
            ->where('status', 'published')
            ->latest()
            ->get([
                'id',
                'batch_id',
                'course_id',
                'course_section_id',
                'course_lesson_id',
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
                'course',
                'section',
                'lesson',
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

    private function visibleQuizzes(User $user): Builder
    {
        return Quiz::query()->where(function ($query) use ($user) {
            // Course-based quizzes, including those without batches.
            $query->whereHas('course', function ($courseQuery) use ($user) {
                if (!$user->isAdmin()) {
                    $courseQuery->where('owner_id', $user->id);
                }
            });

            // Legacy batch-only quizzes remain supported.
            $query->orWhereHas('batch.course', function ($courseQuery) use ($user) {
                if (!$user->isAdmin()) {
                    $courseQuery->where('owner_id', $user->id);
                }
            });
        });
    }

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

    private function ensureCoursePlacement(
        array $data,
        User $user
    ): void {
        $courseId = $data['course_id'] ?? null;
        $sectionId = $data['course_section_id'] ?? null;
        $lessonId = $data['course_lesson_id'] ?? null;
        $batchId = $data['batch_id'] ?? null;

        if (!$courseId) {
            abort_unless(
                !$sectionId && !$lessonId,
                422,
                'A course is required for module or lesson placement.'
            );

            // Preserve support for existing batch-only quizzes.
            return;
        }

        $course = Course::query()->findOrFail($courseId);

        abort_unless(
            $user->isAdmin() ||
            (int) $course->owner_id === (int) $user->id,
            404,
            'The selected course was not found.'
        );

        // Batch is optional. If supplied, it must belong to the course.
        if ($batchId) {
            $batch = Batch::query()->findOrFail($batchId);

            abort_unless(
                (int) $batch->course_id === (int) $course->id,
                422,
                'The selected batch must belong to the selected course.'
            );
        }
    }
}
