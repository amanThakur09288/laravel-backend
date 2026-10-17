<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Admin - List Services
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        $services = Service::latest()->get();

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin - Create Service
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_name' => [
                'required',
                'string',
                'max:255',
            ],

            'short_description' => [
                'required',
                'string',
                'max:1000',
            ],

            'description' => [
                'required',
                'string',
            ],

            'offerings' => [
                'nullable',
                'array',
            ],

            'process' => [
                'nullable',
                'array',
            ],

            'faqs' => [
                'nullable',
                'array',
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'Active',
                    'Inactive',
                ]),
            ],
        ]);

        $validated['offerings'] =
            $validated['offerings'] ?? [];

        $validated['process'] =
            $validated['process'] ?? [];

        $validated['faqs'] =
            $validated['faqs'] ?? [];

        $validated['status'] =
            $validated['status'] ?? 'Active';

        $validated['slug'] = $this->generateUniqueSlug(
            $validated['service_name']
        );

        $service = Service::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Service created successfully.',
            'data' => $service,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin - Show Service
    |--------------------------------------------------------------------------
    */

    public function show(Service $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin - Update Service
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Service $service
    ): JsonResponse {
        $validated = $request->validate([
            'service_name' => [
                'required',
                'string',
                'max:255',
            ],

            'short_description' => [
                'required',
                'string',
                'max:1000',
            ],

            'description' => [
                'required',
                'string',
            ],

            'offerings' => [
                'nullable',
                'array',
            ],

            'process' => [
                'nullable',
                'array',
            ],

            'faqs' => [
                'nullable',
                'array',
            ],

            'status' => [
                'required',
                Rule::in([
                    'Active',
                    'Inactive',
                ]),
            ],
        ]);

        $validated['offerings'] =
            $validated['offerings'] ?? [];

        $validated['process'] =
            $validated['process'] ?? [];

        $validated['faqs'] =
            $validated['faqs'] ?? [];

        /*
         * Generate a new slug only when
         * the service name has changed.
         */
        if (
            $validated['service_name'] !==
            $service->service_name
        ) {
            $validated['slug'] = $this->generateUniqueSlug(
                $validated['service_name'],
                $service->id
            );
        }

        $service->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Service updated successfully.',
            'data' => $service->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin - Delete Service
    |--------------------------------------------------------------------------
    */

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Public - Service List
    |--------------------------------------------------------------------------
    |
    | Used by:
    | - Header Services dropdown
    | - Client Services listing
    |
    */

    public function publicIndex(): JsonResponse
    {
        $services = Service::query()
            ->where('status', 'Active')
            ->select([
                'id',
                'service_name',
                'slug',
                'short_description',
            ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Public - Service Details
    |--------------------------------------------------------------------------
    |
    | Used by:
    | /services/{slug}
    |
    */

    public function publicShow(string $slug): JsonResponse
    {
        $service = Service::query()
            ->where('status', 'Active')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $service,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Unique Slug
    |--------------------------------------------------------------------------
    */

    private function generateUniqueSlug(
        string $serviceName,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($serviceName);

        if ($baseSlug === '') {
            $baseSlug = 'service';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            Service::where('slug', $slug)
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