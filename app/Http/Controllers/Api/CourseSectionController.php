<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseSectionRequest;
use App\Http\Requests\UpdateCourseSectionRequest;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class CourseSectionController extends Controller
{
    public function index(Course $course): JsonResponse
    {
        $this->ensureCourseAccess($course, request()->user());

        $sections = $course->sections()
            ->withCount('lessons')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sections,
        ]);
    }

    public function store(
        StoreCourseSectionRequest $request,
        Course $course
    ): JsonResponse {
        $this->ensureCourseAccess($course, $request->user());

        $validated = $request->validated();

        abort_unless(
            (int) $validated['course_id'] === (int) $course->id,
            422,
            'The selected course does not match the requested course.'
        );

        $storedFiles = [];

        try {
            $section = DB::transaction(function () use (
                $validated,
                &$storedFiles,
                $course,
                $request
            ) {
                $section = $course->sections()->create([
                    'title' => trim($validated['title']),
                    'description' => $validated['description'] ?? null,
                    'sort_order' => $validated['sort_order'],
                    'status' => $validated['status'],
                ]);

                foreach ($validated['lessons'] as $index => $lessonData) {
                    $file = $request->file("lessons.$index.video");

                    if (!$file) {
                        throw new \RuntimeException(
                            'Video file is missing for lesson ' . ($index + 1) . '.'
                        );
                    }

                    $path = $file->store(
                        "course-videos/{$section->id}",
                        'local'
                    );

                    if (!$path) {
                        throw new \RuntimeException(
                            'Unable to store video for lesson ' . ($index + 1) . '.'
                        );
                    }

                    $storedFiles[] = $path;

                    $section->lessons()->create([
                        'title' => trim($lessonData['title']),
                        'description' => $lessonData['description'] ?? null,
                        'type' => 'video',
                        'video_path' => $path,
                        'video_original_name' => $file->getClientOriginalName(),
                        'sort_order' => $index + 1,
                        'duration_seconds' => $lessonData['duration_seconds'] ?? null,
                        'is_preview' => (bool) ($lessonData['is_preview'] ?? false),
                        'status' => $lessonData['status'],
                    ]);
                }

                return $section->load('lessons');
            });

            $this->attachVideoUrls($section);

            return response()->json([
                'success' => true,
                'message' => 'Video section created successfully.',
                'data' => $section,
            ], 201);

        } catch (Throwable $exception) {
            if (!empty($storedFiles)) {
                Storage::disk('local')->delete($storedFiles);
            }

            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create video section.',
            ], 500);
        }
    }

    public function show(
        Course $course,
        CourseSection $section
    ): JsonResponse {
        $this->ensureCourseAccess($course, request()->user());
        $this->ensureSectionBelongsToCourse($course, $section);

        $section->load([
            'lessons' => fn ($query) =>
                $query->orderBy('sort_order'),
        ]);

        $this->attachVideoUrls($section);

        return response()->json([
            'success' => true,
            'data' => $section,
        ]);
    }

    public function update(
        UpdateCourseSectionRequest $request,
        Course $course,
        CourseSection $section
    ): JsonResponse {
        $this->ensureCourseAccess($course, $request->user());
        $this->ensureSectionBelongsToCourse($course, $section);

        $validated = $request->validated();

        // The destination course must independently be accessible.
        $targetCourse = Course::query()->findOrFail(
            (int) $validated['course_id']
        );

        $this->ensureCourseAccess($targetCourse, $request->user());

        $newFiles = [];
        $oldFiles = [];

        try {
            DB::transaction(function () use (
                $validated,
                $section,
                $course,
                $targetCourse,
                &$newFiles,
                &$oldFiles,
                $request
            ) {
                $section = CourseSection::query()
                    ->whereKey($section->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Re-check the parent relationship after acquiring the lock.
                abort_unless(
                    (int) $section->course_id === (int) $course->id,
                    404
                );

                // Moving is allowed only to the independently authorized course.
                $section->update([
                    'course_id' => $targetCourse->id,
                    'title' => trim($validated['title']),
                    'description' => $validated['description'] ?? null,
                    'sort_order' => $validated['sort_order'],
                    'status' => $validated['status'],
                ]);

                $existingLessons = $section->lessons()
                    ->get()
                    ->keyBy('id');

                $processedLessonIds = [];

                foreach ($validated['lessons'] as $index => $lessonData) {
                    $lessonId = $lessonData['id'] ?? null;

                    // Existing lesson.
                    if ($lessonId) {
                        $lesson = $existingLessons->get((int) $lessonId);

                        abort_unless($lesson, 422);

                        $processedLessonIds[] = $lesson->id;

                        $updateData = [
                            'title' => trim($lessonData['title']),
                            'description' => $lessonData['description'] ?? null,
                            'type' => 'video',
                            'sort_order' => $index + 1,
                            'duration_seconds' => $lessonData['duration_seconds'] ?? null,
                            'is_preview' => (bool) ($lessonData['is_preview'] ?? false),
                            'status' => $lessonData['status'],
                        ];

                        $file = $request->file("lessons.$index.video");

                        if ($file) {
                            $newPath = $file->store(
                                "course-videos/{$section->id}",
                                'local'
                            );

                            if (!$newPath) {
                                throw new \RuntimeException(
                                    'Unable to store replacement video for lesson ' .
                                    ($index + 1) . '.'
                                );
                            }

                            $newFiles[] = $newPath;

                            if ($lesson->video_path) {
                                $oldFiles[] = $lesson->video_path;
                            }

                            $updateData['video_path'] = $newPath;
                            $updateData['video_original_name'] =
                                $file->getClientOriginalName();
                        }

                        $lesson->update($updateData);

                        continue;
                    }

                    // New lesson.
                    $file = $request->file("lessons.$index.video");

                    if (!$file) {
                        throw new \RuntimeException(
                            'Video file is missing for lesson ' . ($index + 1) . '.'
                        );
                    }

                    $path = $file->store(
                        "course-videos/{$section->id}",
                        'local'
                    );

                    if (!$path) {
                        throw new \RuntimeException(
                            'Unable to store video for lesson ' . ($index + 1) . '.'
                        );
                    }

                    $newFiles[] = $path;

                    $lesson = $section->lessons()->create([
                        'title' => trim($lessonData['title']),
                        'description' => $lessonData['description'] ?? null,
                        'type' => 'video',
                        'video_path' => $path,
                        'video_original_name' => $file->getClientOriginalName(),
                        'sort_order' => $index + 1,
                        'duration_seconds' => $lessonData['duration_seconds'] ?? null,
                        'is_preview' => (bool) ($lessonData['is_preview'] ?? false),
                        'status' => $lessonData['status'],
                    ]);

                    $processedLessonIds[] = $lesson->id;
                }

                // Remove lessons omitted from the submitted edit form.
                foreach ($existingLessons as $lesson) {
                    if (!in_array(
                        $lesson->id,
                        $processedLessonIds,
                        true
                    )) {
                        if ($lesson->video_path) {
                            $oldFiles[] = $lesson->video_path;
                        }

                        $lesson->delete();
                    }
                }
            });

            // Remove replaced/deleted files only after the DB transaction succeeds.
            if (!empty($oldFiles)) {
                Storage::disk('local')->delete($oldFiles);
            }

            $section->refresh()->load('lessons');

            $this->attachVideoUrls($section);

            return response()->json([
                'success' => true,
                'message' => 'Video section updated successfully.',
                'data' => $section,
            ]);

        } catch (Throwable $exception) {
            if (!empty($newFiles)) {
                Storage::disk('local')->delete($newFiles);
            }

            // Preserve proper 404/422 responses rather than converting them to 500.
            if (
                $exception instanceof HttpExceptionInterface ||
                $exception instanceof ValidationException ||
                $exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
            ) {
                throw $exception;
            }

            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update video section.',
            ], 500);
        }
    }

    public function destroy(
        Course $course,
        CourseSection $section
    ): JsonResponse {
        $this->ensureCourseAccess($course, request()->user());
        $this->ensureSectionBelongsToCourse($course, $section);

        $videoFiles = $section->lessons()
            ->whereNotNull('video_path')
            ->pluck('video_path')
            ->filter()
            ->values()
            ->all();

        try {
            DB::transaction(function () use ($section) {
                $section->delete();
            });

            if (!empty($videoFiles)) {
                Storage::disk('local')->delete($videoFiles);
            }

            return response()->json([
                'success' => true,
                'message' => 'Video section deleted successfully.',
            ]);

        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete video section.',
            ], 500);
        }
    }

    private function ensureCourseAccess(
        Course $course,
        ?User $user
    ): void {
        abort_unless(
            $user && (
                $user->isAdmin() ||
                (int) $course->owner_id === (int) $user->id
            ),
            404
        );
    }

    private function ensureSectionBelongsToCourse(
        Course $course,
        CourseSection $section
    ): void {
        abort_unless(
            (int) $section->course_id === (int) $course->id,
            404
        );
    }

    private function attachVideoUrls(CourseSection $section): void
    {
        $section->lessons->each(function ($lesson) use ($section) {
            $lesson->setAttribute(
                'video_url',
                $lesson->video_path
                    ? route(
                        'admin.sections.lessons.video',
                        [
                            'section' => $section->id,
                            'lesson' => $lesson->id,
                        ]
                    )
                    : null
            );
        });
    }
}
