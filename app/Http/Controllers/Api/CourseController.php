<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    /**
     * Display all courses for admin.
     */
    public function index(): JsonResponse
    {
        $courses = Course::latest()->get();

        return response()->json([
            'success' => true,
            'data' => $courses,
        ]);
    }

    /**
     * Store a new course.
     */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['learning_outcomes'] =
            $validated['learning_outcomes'] ?? [];

        $validated['curriculum'] =
            $validated['curriculum'] ?? [];

        $validated['prerequisites'] =
            $validated['prerequisites'] ?? [];

        $validated['who_should_join'] =
            $validated['who_should_join'] ?? [];

        $validated['course_features'] =
            $validated['course_features'] ?? [];

        $validated['faqs'] =
            $validated['faqs'] ?? [];

        $validated['status'] =
            $validated['status'] ?? 'Active';

        /*
        |--------------------------------------------------------------------------
        | Generate slug on backend
        |--------------------------------------------------------------------------
        */

        $validated['slug'] = $this->generateUniqueSlug(
            $validated['course_name']
        );

        $course = Course::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully.',
            'data' => $course,
        ], 201);
    }

    /**
     * Display one course for admin.
     *
     * Admin continues using ID.
     */
    public function show(Course $course): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $course,
        ]);
    }

    /**
     * Update course.
     */
    public function update(
        UpdateCourseRequest $request,
        Course $course
    ): JsonResponse {
        $validated = $request->validated();

        $validated['learning_outcomes'] =
            $validated['learning_outcomes'] ?? [];

        $validated['curriculum'] =
            $validated['curriculum'] ?? [];

        $validated['prerequisites'] =
            $validated['prerequisites'] ?? [];

        $validated['who_should_join'] =
            $validated['who_should_join'] ?? [];

        $validated['course_features'] =
            $validated['course_features'] ?? [];

        $validated['faqs'] =
            $validated['faqs'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Regenerate slug only if course name changed
        |--------------------------------------------------------------------------
        */

        if (
            isset($validated['course_name']) &&
            $validated['course_name'] !== $course->course_name
        ) {
            $validated['slug'] = $this->generateUniqueSlug(
                $validated['course_name'],
                $course->id
            );
        }

        $course->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Course updated successfully.',
            'data' => $course->fresh(),
        ]);
    }

    /**
     * Display public courses.
     */
    public function publicIndex(): JsonResponse
    {
        $courses = Course::published()
            ->select([
                'id',
                'course_name',
                'slug',
                'category',
                'level',
                'short_description',
                'duration',
                'total_hours',
                'learning_outcomes',
                'course_features',
            ])
            ->withCount([
                'batches as upcoming_batches_count' => fn ($query) =>
                    $query->whereDate(
                        'end_date',
                        '>=',
                        now()
                    ),
            ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $courses,
        ]);
    }

    /**
     * Display one public course using slug.
     */
    public function publicShow(string $slug): JsonResponse
    {
        $course = Course::published()
            ->select([
                'id',
                'course_name',
                'slug',
                'category',
                'level',
                'short_description',
                'course_description',
                'duration',
                'total_hours',
                'learning_outcomes',
                'curriculum',
                'prerequisites',
                'who_should_join',
                'course_features',
                'faqs',
            ])
            ->with([
                'batches' => fn ($query) =>
                    $query
                        ->whereDate(
                            'end_date',
                            '>=',
                            now()
                        )
                        ->orderBy('start_date')
                        ->select([
                            'id',
                            'course_id',
                            'slug',
                            'batch_name',
                            'start_date',
                            'end_date',
                            'duration_text',
                            'class_days',
                            'class_start_time',
                            'class_end_time',
                            'mode',
                            'location',
                            'total_seats',
                            'available_seats',
                            'status',
                            'fee',
                            'discounted_fee',
                            'offer_text',
                        ]),
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $course,
        ]);
    }

    /**
     * Delete course.
     */
    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json([
            'success' => true,
            'message' => 'Course deleted successfully.',
        ]);
    }

    /**
     * Generate a unique course slug.
     */
    private function generateUniqueSlug(
        string $courseName,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($courseName);

        if ($baseSlug === '') {
            $baseSlug = 'course';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            Course::where('slug', $slug)
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