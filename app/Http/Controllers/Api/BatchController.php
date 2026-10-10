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
    public function index(): JsonResponse
    {
        $batches = Batch::query()
            ->orderBy('start_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $batches,
        ]);
    }

    public function publicIndex(): JsonResponse
{
    $batches = Batch::query()
        ->whereDate('end_date', '>=', now())
        ->orderBy('start_date')
        ->get();

    return response()->json(['success' => true, 'data' => $batches]);
}

public function publicShow(string $slug): JsonResponse
{
    $batch = Batch::where('slug', $slug)->firstOrFail();

    return response()->json(['success' => true, 'data' => $batch]);
}

    public function store(
        StoreBatchRequest $request
    ): JsonResponse {
        $data = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Generate slug automatically
        |--------------------------------------------------------------------------
        */

        $data['slug'] =
            $this->generateUniqueSlug(
                $data['batch_name']
            );

        $batch = Batch::create($data);

        return response()->json([
            'success' => true,
            'message' =>
                'Batch created successfully.',
            'data' => $batch,
        ], 201);
    }

    public function show(
        Batch $batch
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $batch,
        ]);
    }

    public function update(
        UpdateBatchRequest $request,
        Batch $batch
    ): JsonResponse {
        $data = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Regenerate slug when Batch Name changes
        |--------------------------------------------------------------------------
        */

        if (
            $batch->batch_name !==
            $data['batch_name']
        ) {
            $data['slug'] =
                $this->generateUniqueSlug(
                    $data['batch_name'],
                    $batch->id
                );
        }

        $batch->update($data);

        return response()->json([
            'success' => true,
            'message' =>
                'Batch updated successfully.',
            'data' => $batch->fresh(),
        ]);
    }

    public function destroy(
        Batch $batch
    ): JsonResponse {
        $batch->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Batch deleted successfully.',
        ]);
    }

    private function generateUniqueSlug(
        string $batchName,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($batchName);

        /*
        |--------------------------------------------------------------------------
        | Safety fallback
        |--------------------------------------------------------------------------
        */

        if ($baseSlug === '') {
            $baseSlug = 'batch';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            Batch::query()
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    function ($query) use ($ignoreId) {
                        $query->where(
                            'id',
                            '!=',
                            $ignoreId
                        );
                    }
                )
                ->exists()
        ) {
            $slug =
                $baseSlug .
                '-' .
                $counter;

            $counter++;
        }

        return $slug;
    }
}