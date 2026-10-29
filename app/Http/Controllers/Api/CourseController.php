<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Models\CourseLesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CourseController extends Controller
{
    public function index(): JsonResponse
    {
        $courses = Course::with([
            'sections.lessons',
        ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $courses,
        ]);
    }

    public function store(StoreCourseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['learning_outcomes'] = $validated['learning_outcomes'] ?? [];
        $validated['curriculum'] = $validated['curriculum'] ?? [];
        $validated['prerequisites'] = $validated['prerequisites'] ?? [];
        $validated['who_should_join'] = $validated['who_should_join'] ?? [];
        $validated['course_features'] = $validated['course_features'] ?? [];
        $validated['faqs'] = $validated['faqs'] ?? [];
        $validated['status'] = $validated['status'] ?? 'Active';

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

    public function show(Course $course): JsonResponse
    {
        $course->load([
            'sections.lessons',
        ]);

        return response()->json([
            'success' => true,
            'data' => $course,
        ]);
    }

    public function update(
        UpdateCourseRequest $request,
        Course $course
    ): JsonResponse {
        $validated = $request->validated();

        $validated['learning_outcomes'] = $validated['learning_outcomes'] ?? [];
        $validated['curriculum'] = $validated['curriculum'] ?? [];
        $validated['prerequisites'] = $validated['prerequisites'] ?? [];
        $validated['who_should_join'] = $validated['who_should_join'] ?? [];
        $validated['course_features'] = $validated['course_features'] ?? [];
        $validated['faqs'] = $validated['faqs'] ?? [];

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

                'sections' => fn ($query) =>
                    $query
                        ->where('status', 'published')
                        ->with([
                            'lessons' => fn ($lessonQuery) =>
                                $lessonQuery
                                    ->where('status', 'published')
                                    ->select([
                                        'id',
                                        'course_section_id',
                                        'title',
                                        'description',
                                        'type',
                                        'sort_order',
                                        'duration_seconds',
                                        'is_preview',
                                        'status',
                                    ]),
                        ])
                        ->select([
                            'id',
                            'course_id',
                            'title',
                            'description',
                            'sort_order',
                            'status',
                        ]),
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Add playback URL without exposing video_path
        |--------------------------------------------------------------------------
        */

        $course->sections->each(function ($section) use ($course) {
            $section->lessons->each(function ($lesson) use ($course) {
                $lesson->setAttribute(
                    'video_url',
                    route(
                        'public.courses.lessons.video',
                        [
                            'slug' => $course->slug,
                            'lesson' => $lesson->id,
                        ]
                    )
                );
            });
        });

        return response()->json([
            'success' => true,
            'data' => $course,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Secure Video Playback
    |--------------------------------------------------------------------------
    */

    public function streamLessonVideo(
        string $slug,
        CourseLesson $lesson
    ): BinaryFileResponse|Response {
        $course = Course::published()
            ->where('slug', $slug)
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Make sure the lesson actually belongs to this course
        |--------------------------------------------------------------------------
        */

        $section = $course->sections()
            ->whereKey($lesson->course_section_id)
            ->where('status', 'published')
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Make sure lesson belongs to the selected published section
        |--------------------------------------------------------------------------
        */

        abort_unless(
            $lesson->status === 'published',
            404
        );

        /*
        |--------------------------------------------------------------------------
        | Non-preview videos require authentication
        |--------------------------------------------------------------------------
        |
        | We currently do not have an enrollment/access table in the project.
        | Therefore:
        |
        | - Preview video  -> public
        | - Normal video   -> authenticated user required
        |
        | Once your enrollment system exists, this is where we will check
        | whether the authenticated user is enrolled in this course/batch.
        |
        */

        if (!$lesson->is_preview && !request()->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required to watch this video.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Make sure a video actually exists
        |--------------------------------------------------------------------------
        */

        if (!$lesson->video_path) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (!$disk->exists($lesson->video_path)) {
            abort(404);
        }

        $absolutePath = $disk->path($lesson->video_path);

        /*
        |--------------------------------------------------------------------------
        | Determine MIME type
        |--------------------------------------------------------------------------
        */

        $mimeType = $disk->mimeType($lesson->video_path)
            ?: 'video/mp4';

        /*
        |--------------------------------------------------------------------------
        | Stream inline
        |--------------------------------------------------------------------------
        |
        | BinaryFileResponse supports Range requests, which is important for
        | browser video seeking/resuming.
        |
        */

        return response()
            ->file($absolutePath, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]);
    }

    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json([
            'success' => true,
            'message' => 'Course deleted successfully.',
        ]);
    }

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