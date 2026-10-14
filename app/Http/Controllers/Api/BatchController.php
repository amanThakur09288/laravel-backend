<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBatchRequest;
use App\Http\Requests\UpdateBatchRequest;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class BatchController extends Controller
{
    /* =====================================================
       ADMIN (find batch by id)
    ===================================================== */

    public function index(): JsonResponse
    {
        $batches = Batch::with('course')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $batches,
        ]);
    }

    public function store(StoreBatchRequest $request): JsonResponse
    {
        $data = $request->validated();

        // react-batch-01 style slug, always unique
        $data['slug'] = $this->generateUniqueSlug($data['batch_name']);

        $batch = Batch::create($data)->load('course');

        return response()->json([
            'success' => true,
            'message' => 'Batch created successfully.',
            'data' => $batch,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $batch = Batch::with('course')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $batch,
        ]);
    }

    public function update(UpdateBatchRequest $request, string $id): JsonResponse
    {
        $batch = Batch::findOrFail($id);

        $data = $request->validated();

        // Only change the slug when the batch name changes
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
        Batch::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Batch deleted successfully.',
        ]);
    }

    /* =====================================================
       PUBLIC (website, find batch by slug)
    ===================================================== */

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
        $batch = Batch::with('course')->where('slug', $slug)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $batch,
        ]);
    }

    /* =====================================================
       HELPERS
    ===================================================== */

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
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }
}