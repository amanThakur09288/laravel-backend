<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CourseLessonController extends Controller
{
    public function index(CourseSection $section): JsonResponse
    {
        $this->ensureCourseAccess($section, request()->user());

        $lessons = $section->lessons()->get();

        $this->attachVideoUrls($section, $lessons);

        return response()->json([
            'success' => true,
            'data' => $lessons,
        ]);
    }

    public function show(
        CourseSection $section,
        CourseLesson $lesson
    ): JsonResponse {
        $this->ensureCourseAccess($section, request()->user());

        $this->ensureLessonBelongsToSection($section, $lesson);

        $lesson->setAttribute(
            'video_url',
            $this->videoUrl($section, $lesson)
        );

        return response()->json([
            'success' => true,
            'data' => $lesson,
        ]);
    }

    public function destroy(
        CourseSection $section,
        CourseLesson $lesson
    ): JsonResponse {
        $this->ensureCourseAccess($section, request()->user());

        $this->ensureLessonBelongsToSection($section, $lesson);

        $videoPath = $lesson->video_path;

        $lesson->delete();

        if ($videoPath) {
            Storage::disk('local')->delete($videoPath);
        }

        return response()->json([
            'success' => true,
            'message' => 'Video lesson deleted successfully.',
        ]);
    }

    public function video(
        CourseSection $section,
        CourseLesson $lesson
    ) {
        $this->ensureCourseAccess($section, request()->user());

        $this->ensureLessonBelongsToSection($section, $lesson);

        abort_if(
            !$lesson->video_path,
            404,
            'Video not found.'
        );

        $disk = Storage::disk('local');

        abort_if(
            !$disk->exists($lesson->video_path),
            404,
            'Video file not found.'
        );

        $absolutePath = $disk->path($lesson->video_path);

        $mimeType = $disk->mimeType(
            $lesson->video_path
        ) ?: 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' =>
                'inline; filename="' .
                addslashes(
                    $lesson->video_original_name ?: 'video'
                ) .
                '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Check ownership through the parent course.
     */
    private function ensureCourseAccess(
        CourseSection $section,
        ?User $user
    ): void {
        $course = $section->course;

        abort_unless(
            $user && $course && (
                $user->isAdmin() ||
                (int) $course->owner_id === (int) $user->id
            ),
            404
        );
    }

    /**
     * Make sure the lesson belongs to the requested section.
     */
    private function ensureLessonBelongsToSection(
        CourseSection $section,
        CourseLesson $lesson
    ): void {
        abort_unless(
            (int) $lesson->course_section_id === (int) $section->id,
            404
        );
    }

    private function videoUrl(
        CourseSection $section,
        CourseLesson $lesson
    ): ?string {
        if (!$lesson->video_path) {
            return null;
        }

        return route(
            'admin.sections.lessons.video',
            [
                'section' => $section->id,
                'lesson' => $lesson->id,
            ]
        );
    }

    private function attachVideoUrls(
        CourseSection $section,
        $lessons
    ): void {
        $lessons->each(function ($lesson) use ($section) {
            $lesson->setAttribute(
                'video_url',
                $this->videoUrl($section, $lesson)
            );
        });
    }
}