<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizRequest;
use App\Http\Requests\UpdateQuizRequest;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class QuizController extends Controller
{
    /**
     * Display all quizzes.
     */
    public function index(): JsonResponse
    {
        $quizzes = Quiz::with([
            'batch',
            'creator:id,name,email',
        ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $quizzes,
        ]);
    }

    /**
     * Store a newly created quiz.
     */
    public function store(StoreQuizRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['created_by'] = $request->user()->id;

        $data['slug'] = $this->generateUniqueSlug(
            $data['title']
        );

        $data['shuffle_questions'] =
            $data['shuffle_questions'] ?? false;

        $data['shuffle_options'] =
            $data['shuffle_options'] ?? false;

        $data['auto_submit'] =
            $data['auto_submit'] ?? true;

        $data['show_result'] =
            $data['show_result'] ?? true;

        $data['show_correct_answers'] =
            $data['show_correct_answers'] ?? true;

        $data['status'] =
            $data['status'] ?? 'draft';

        $quiz = Quiz::create($data);

        $quiz->load([
            'batch',
            'creator:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Quiz created successfully.',
            'data' => $quiz,
        ], 201);
    }

    /**
     * Display a specific quiz.
     */
    public function show(string $id): JsonResponse
    {
        $quiz = Quiz::with([
            'batch',
            'creator:id,name,email',
            'questions.options',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $quiz,
        ]);
    }

    /**
     * Update a quiz.
     */
    public function update(
        UpdateQuizRequest $request,
        string $id
    ): JsonResponse {
        $quiz = Quiz::findOrFail($id);

        $data = $request->validated();

        if (
            isset($data['title']) &&
            $data['title'] !== $quiz->title
        ) {
            $data['slug'] = $this->generateUniqueSlug(
                $data['title'],
                $quiz->id
            );
        }

        $quiz->update($data);

        $quiz->load([
            'batch',
            'creator:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Quiz updated successfully.',
            'data' => $quiz,
        ]);
    }

    /**
     * Delete a quiz.
     */
    public function destroy(string $id): JsonResponse
    {
        $quiz = Quiz::findOrFail($id);

        /*
         * Do not allow deletion if attempts already exist.
         * Attempt history should be preserved.
         */
        if ($quiz->attempts()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This quiz cannot be deleted because students have already attempted it.',
            ], 422);
        }

        $quiz->delete();

        return response()->json([
            'success' => true,
            'message' => 'Quiz deleted successfully.',
        ]);
    }

    /**
     * Generate a unique quiz slug.
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
            Quiz::where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($query) =>
                        $query->where('id', '!=', $ignoreId)
                )
                ->exists()
        ) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}