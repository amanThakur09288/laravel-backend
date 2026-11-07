<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CourseController extends Controller
{
    public function index(): JsonResponse
    {
        $courses = $this->visibleCourses(request()->user())
            ->with(['sections.lessons'])
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

        // Ownership is assigned by the server, never by the client.
        $course = new Course($validated);
        $course->owner_id = $request->user()->id;
        $course->save();

        return response()->json([
            'success' => true,
            'message' => 'Course created successfully.',
            'data' => $course,
        ], 201);
    }

    public function show(Course $course): JsonResponse
    {
        $this->authorizeCourseAccess($course, request()->user());

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
        $this->authorizeCourseAccess($course, $request->user());

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

        // Never accept a changed owner_id from the request.
        $course->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Course updated successfully.',
            'data' => $course->fresh(),
        ]);
    }

    public function destroy(Course $course): JsonResponse
    {
        $this->authorizeCourseAccess($course, request()->user());

        $course->delete();

        return response()->json([
            'success' => true,
            'message' => 'Course deleted successfully.',
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
                    $query->whereDate('end_date', '>=', now()),
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
                        ->whereDate('end_date', '>=', now())
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

        // Add playback URL without exposing the physical video path.
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

    public function streamLessonVideo(
        string $slug,
        CourseLesson $lesson
    ): BinaryFileResponse|Response {
        $course = Course::published()
            ->where('slug', $slug)
            ->firstOrFail();

        // The lesson must belong to a published section of this course.
        $course->sections()
            ->whereKey($lesson->course_section_id)
            ->where('status', 'published')
            ->firstOrFail();

        abort_unless($lesson->status === 'published', 404);

        /*
         * Preview videos are public.
         * Private videos require authentication.
         * Enrollment checks can be added when enrollment is implemented.
         */
        if (!$lesson->is_preview) {
            $viewer = request()->user();

            if (!$viewer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required to watch this video.',
                ], 401);
            }

            abort_unless($viewer->is_active, 403);

            // A Teacher cannot use the public playback URL to access
            // another Teacher's private course videos.
            if ($viewer->isTeacher()) {
                $this->authorizeCourseAccess($course, $viewer);
            } elseif (!$viewer->isAdmin() && !$viewer->isStudent()) {
                abort(403);
            }
        }

        if (!$lesson->video_path) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (!$disk->exists($lesson->video_path)) {
            abort(404);
        }

        $absolutePath = $disk->path($lesson->video_path);

        $mimeType = $disk->mimeType($lesson->video_path)
            ?: 'video/mp4';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Main Admin can see every course.
     * Teachers can see only courses they own.
     */
    private function visibleCourses(User $user): Builder
    {
        return Course::query()->when(
            !$user->isAdmin(),
            fn (Builder $query) =>
                $query->where('owner_id', $user->id)
        );
    }

    private function authorizeCourseAccess(
        Course $course,
        User $user
    ): void {
        abort_unless(
            $user->isAdmin() ||
            (int) $course->owner_id === (int) $user->id,
            404
        );
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
