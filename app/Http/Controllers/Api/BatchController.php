<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBatchRequest;
use App\Http\Requests\UpdateBatchRequest;
use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class BatchController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | ADMIN
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        $batches = $this->visibleBatches(request()->user())
            ->with('course')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $batches,
        ]);
    }

    public function store(StoreBatchRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Verify that the selected course belongs to this admin.
        $this->ensureCourseAccess(
            (int) $data['course_id'],
            $request->user()
        );

        $data['slug'] = $this->generateUniqueSlug(
            $data['batch_name']
        );

        $batch = Batch::create($data)->load('course');

        return response()->json([
            'success' => true,
            'message' => 'Batch created successfully.',
            'data' => $batch,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $batch = $this->visibleBatches(request()->user())
            ->with('course')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $batch,
        ]);
    }

    public function update(
        UpdateBatchRequest $request,
        string $id
    ): JsonResponse {
        $batch = $this->visibleBatches($request->user())
            ->findOrFail($id);

        $data = $request->validated();

        // Verify the destination course too, in case course_id is changed.
        $this->ensureCourseAccess(
            (int) $data['course_id'],
            $request->user()
        );

        if ($data['batch_name'] !== $batch->batch_name) {
            $data['slug'] = $this->generateUniqueSlug(
                $data['batch_name'],
                $batch->id
            );
        }

        $batch->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Batch updated successfully.',
            'data' => $batch->load('course'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $batch = $this->visibleBatches(request()->user())
            ->findOrFail($id);

        $batch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Batch deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLIC WEBSITE
    |--------------------------------------------------------------------------
    */

    public function publicIndex(): JsonResponse
    {
        $batches = Batch::with('course')
            ->whereDate('end_date', '>=', now())
            ->orderBy('start_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $batches,
        ]);
    }

    public function publicShow(string $slug): JsonResponse
    {
        $batch = Batch::with('course')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $batch,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | OWNERSHIP HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Main Admin can access all batches.
     * Teachers can access batches belonging to their courses.
     */
    private function visibleBatches(User $user)
    {
        return Batch::query()->when(
            !$user->isAdmin(),
            fn ($query) =>
                $query->whereHas(
                    'course',
                    fn ($courseQuery) =>
                        $courseQuery->where('owner_id', $user->id)
                )
        );
    }

    /**
     * A Teacher can attach a batch only to their own course.
     */
    private function ensureCourseAccess(
        int $courseId,
        User $user
    ): void {
        $course = Course::findOrFail($courseId);

        abort_unless(
            $user->isAdmin() ||
            (int) $course->owner_id === (int) $user->id,
            403,
            'You cannot manage batches for this course.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SLUG HELPER
    |--------------------------------------------------------------------------
    */

    private function generateUniqueSlug(
        string $batchName,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($batchName);

        if ($baseSlug === '') {
            $baseSlug = 'batch';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            Batch::where('slug', $slug)
                ->when(
                    $ignoreId !== null,
                    fn ($query) =>
                        $query->where('id', '!=', $ignoreId)
                )
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}