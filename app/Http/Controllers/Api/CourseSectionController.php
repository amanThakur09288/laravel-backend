<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseSectionRequest;
use App\Http\Requests\UpdateCourseSectionRequest;
use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CourseSectionController extends Controller
{
    public function index(Course $course): JsonResponse
    {
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
        $validated = $request->validated();

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

                    /*
                    |--------------------------------------------------------------------------
                    | Get uploaded video for this lesson
                    |--------------------------------------------------------------------------
                    */

                    $file = $request->file(
                        "lessons.$index.video"
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Make sure video exists
                    |--------------------------------------------------------------------------
                    */

                    if (!$file) {
                        throw new \RuntimeException(
                            "Video file is missing for lesson " . ($index + 1) . "."
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Store video
                    |--------------------------------------------------------------------------
                    */

                    $path = $file->store(
                        "course-videos/{$section->id}",
                        'local'
                    );

                    if (!$path) {
                        throw new \RuntimeException(
                            "Unable to store video for lesson " . ($index + 1) . "."
                        );
                    }

                    $storedFiles[] = $path;

                    /*
                    |--------------------------------------------------------------------------
                    | Create lesson
                    |--------------------------------------------------------------------------
                    */

                    $section->lessons()->create([
                        'title' => trim($lessonData['title']),
                        'description' => $lessonData['description'] ?? null,
                        'type' => 'video',
                        'video_path' => $path,
                        'video_original_name' =>
                            $file->getClientOriginalName(),
                        'sort_order' => $index + 1,
                        'duration_seconds' =>
                            $lessonData['duration_seconds'] ?? null,
                        'is_preview' =>
                            (bool) ($lessonData['is_preview'] ?? false),
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

            /*
            |--------------------------------------------------------------------------
            | Remove uploaded files if database operation fails
            |--------------------------------------------------------------------------
            */

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
        $this->ensureSectionBelongsToCourse(
            $course,
            $section
        );

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
        $this->ensureSectionBelongsToCourse(
            $course,
            $section
        );

        $validated = $request->validated();

        $newFiles = [];
        $oldFiles = [];

        try {
            DB::transaction(function () use (
                $validated,
                $section,
                &$newFiles,
                &$oldFiles,
                $request
            ) {
                $section = CourseSection::query()
                    ->whereKey($section->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $section->update([
                    'course_id' => $validated['course_id'],
                    'title' => trim($validated['title']),
                    'description' => $validated['description'] ?? null,
                    'sort_order' => $validated['sort_order'],
                    'status' => $validated['status'],
                ]);

                $existingLessons = $section->lessons()
                    ->get()
                    ->keyBy('id');

                $processedLessonIds = [];

                foreach (
                    $validated['lessons']
                    as $index => $lessonData
                ) {
                    $lessonId = $lessonData['id'] ?? null;

                    /*
                    |--------------------------------------------------------------------------
                    | Existing lesson
                    |--------------------------------------------------------------------------
                    */

                    if ($lessonId) {
                        $lesson = $existingLessons->get(
                            (int) $lessonId
                        );

                        abort_unless($lesson, 422);

                        $processedLessonIds[] = $lesson->id;

                        $updateData = [
                            'title' => trim($lessonData['title']),
                            'description' =>
                                $lessonData['description'] ?? null,
                            'type' => 'video',
                            'sort_order' => $index + 1,
                            'duration_seconds' =>
                                $lessonData['duration_seconds'] ?? null,
                            'is_preview' =>
                                (bool) ($lessonData['is_preview'] ?? false),
                            'status' => $lessonData['status'],
                        ];

                        /*
                        |--------------------------------------------------------------------------
                        | Check if new video was uploaded
                        |--------------------------------------------------------------------------
                        */

                        $file = $request->file(
                            "lessons.$index.video"
                        );

                        if ($file) {
                            $newPath = $file->store(
                                "course-videos/{$section->id}",
                                'local'
                            );

                            if (!$newPath) {
                                throw new \RuntimeException(
                                    "Unable to store replacement video for lesson " .
                                    ($index + 1) .
                                    "."
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

                    /*
                    |--------------------------------------------------------------------------
                    | New lesson
                    |--------------------------------------------------------------------------
                    */

                    $file = $request->file(
                        "lessons.$index.video"
                    );

                    if (!$file) {
                        throw new \RuntimeException(
                            "Video file is missing for lesson " .
                            ($index + 1) .
                            "."
                        );
                    }

                    $path = $file->store(
                        "course-videos/{$section->id}",
                        'local'
                    );

                    if (!$path) {
                        throw new \RuntimeException(
                            "Unable to store video for lesson " .
                            ($index + 1) .
                            "."
                        );
                    }

                    $newFiles[] = $path;

                    $lesson = $section->lessons()->create([
                        'title' => trim($lessonData['title']),
                        'description' =>
                            $lessonData['description'] ?? null,
                        'type' => 'video',
                        'video_path' => $path,
                        'video_original_name' =>
                            $file->getClientOriginalName(),
                        'sort_order' => $index + 1,
                        'duration_seconds' =>
                            $lessonData['duration_seconds'] ?? null,
                        'is_preview' =>
                            (bool) ($lessonData['is_preview'] ?? false),
                        'status' => $lessonData['status'],
                    ]);

                    $processedLessonIds[] = $lesson->id;
                }

                /*
                |--------------------------------------------------------------------------
                | Delete lessons removed from edit form
                |--------------------------------------------------------------------------
                */

                foreach ($existingLessons as $lesson) {
                    if (
                        !in_array(
                            $lesson->id,
                            $processedLessonIds,
                            true
                        )
                    ) {
                        if ($lesson->video_path) {
                            $oldFiles[] = $lesson->video_path;
                        }

                        $lesson->delete();
                    }
                }
            });

            /*
            |--------------------------------------------------------------------------
            | Delete old video files after successful transaction
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Remove newly uploaded files if update fails
            |--------------------------------------------------------------------------
            */

            if (!empty($newFiles)) {
                Storage::disk('local')->delete($newFiles);
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
        $this->ensureSectionBelongsToCourse(
            $course,
            $section
        );

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

    private function ensureSectionBelongsToCourse(
        Course $course,
        CourseSection $section
    ): void {
        abort_unless(
            (int) $section->course_id === (int) $course->id,
            404
        );
    }

    private function attachVideoUrls(
        CourseSection $section
    ): void {
        $section->lessons->each(
            function ($lesson) use ($section) {
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
            }
        );
    }
}