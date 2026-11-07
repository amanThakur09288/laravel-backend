<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CourseLesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseSectionOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(User $owner, string $name): Course
    {
        $course = Course::create([
            'course_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)),
            'category' => 'Networking',
            'level' => 'Beginner',
            'status' => 'Active',
        ]);

        $course->owner_id = $owner->id;
        $course->save();

        return $course;
    }

    public function test_teacher_can_list_sections_of_their_own_course(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacherone',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $course = $this->makeCourse($teacher, 'Teacher One Course');

        CourseSection::create([
            'course_id' => $course->id,
            'title' => 'Section One',
            'sort_order' => 1,
            'status' => 'draft',
        ]);

        $this->actingAs($teacher)
            ->getJson("/api/courses/{$course->id}/sections")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Section One');
    }

    public function test_teacher_cannot_list_another_teachers_sections(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacherone',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $otherTeacher = User::factory()->create([
            'username' => 'teachertwo',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $otherCourse = $this->makeCourse(
            $otherTeacher,
            'Teacher Two Course'
        );

        $this->actingAs($teacher)
            ->getJson("/api/courses/{$otherCourse->id}/sections")
            ->assertNotFound();
    }

    public function test_teacher_cannot_view_or_delete_another_teachers_section(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacherone',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $otherTeacher = User::factory()->create([
            'username' => 'teachertwo',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $otherCourse = $this->makeCourse(
            $otherTeacher,
            'Teacher Two Course'
        );

        $section = CourseSection::create([
            'course_id' => $otherCourse->id,
            'title' => 'Private Section',
            'sort_order' => 1,
            'status' => 'draft',
        ]);

        $this->actingAs($teacher)
            ->getJson("/api/courses/{$otherCourse->id}/sections/{$section->id}")
            ->assertNotFound();

        $this->actingAs($teacher)
            ->deleteJson("/api/courses/{$otherCourse->id}/sections/{$section->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('course_sections', [
            'id' => $section->id,
        ]);
    }

    public function test_teacher_cannot_create_section_for_another_teachers_course(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacherone',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $otherTeacher = User::factory()->create([
            'username' => 'teachertwo',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $otherCourse = $this->makeCourse(
            $otherTeacher,
            'Teacher Two Course'
        );

        $this->actingAs($teacher)
            ->postJson("/api/courses/{$otherCourse->id}/sections", [])
            ->assertNotFound();

        $this->assertDatabaseCount('course_sections', 0);
    }

    public function test_section_update_cannot_move_section_to_another_course(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacherone',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $course = $this->makeCourse($teacher, 'Teacher One Course');

        $otherTeacher = User::factory()->create([
            'username' => 'teachertwo',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $otherCourse = $this->makeCourse(
            $otherTeacher,
            'Teacher Two Other Course'
        );

        $section = CourseSection::create([
            'course_id' => $course->id,
            'title' => 'Original Section',
            'sort_order' => 1,
            'status' => 'draft',
        ]);

        $lesson = CourseLesson::create([
            'course_section_id' => $section->id,
            'title' => 'Existing Lesson',
            'type' => 'video',
            'sort_order' => 1,
            'status' => 'draft',
        ]);

        $this->actingAs($teacher)
            ->putJson("/api/courses/{$course->id}/sections/{$section->id}", [
                'course_id' => $otherCourse->id,
                'title' => 'Changed Section',
                'sort_order' => 1,
                'status' => 'draft',
                'lessons' => [[
                    'id' => $lesson->id,
                    'title' => 'Existing Lesson',
                    'type' => 'video',
                    'status' => 'draft',
                ]],
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('course_sections', [
            'id' => $section->id,
            'course_id' => $course->id,
            'title' => 'Original Section',
        ]);
    }

    public function test_teacher_can_move_section_between_their_own_courses(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacherone',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $course = $this->makeCourse($teacher, 'Teacher One Course');

        $targetCourse = $this->makeCourse(
            $teacher,
            'Teacher One Target Course'
        );

        $section = CourseSection::create([
            'course_id' => $course->id,
            'title' => 'Original Section',
            'sort_order' => 1,
            'status' => 'draft',
        ]);

        $lesson = CourseLesson::create([
            'course_section_id' => $section->id,
            'title' => 'Existing Lesson',
            'type' => 'video',
            'sort_order' => 1,
            'status' => 'draft',
        ]);

        $this->actingAs($teacher)
            ->putJson("/api/courses/{$course->id}/sections/{$section->id}", [
                'course_id' => $targetCourse->id,
                'title' => 'Moved Section',
                'sort_order' => 1,
                'status' => 'draft',
                'lessons' => [[
                    'id' => $lesson->id,
                    'title' => 'Existing Lesson',
                    'type' => 'video',
                    'status' => 'draft',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.course_id', $targetCourse->id);

        $this->assertDatabaseHas('course_sections', [
            'id' => $section->id,
            'course_id' => $targetCourse->id,
        ]);
    }

    public function test_teacher_cannot_bypass_course_ownership_through_private_public_video_url(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacherone',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $otherTeacher = User::factory()->create([
            'username' => 'teachertwo',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $course = $this->makeCourse(
            $otherTeacher,
            'Private Video Course'
        );

        $section = CourseSection::create([
            'course_id' => $course->id,
            'title' => 'Published Section',
            'sort_order' => 1,
            'status' => 'published',
        ]);

        $lesson = CourseLesson::create([
            'course_section_id' => $section->id,
            'title' => 'Private Lesson',
            'type' => 'video',
            'video_path' => 'course-videos/private.mp4',
            'sort_order' => 1,
            'status' => 'published',
            'is_preview' => false,
        ]);

        $this->actingAs($teacher)
            ->get("/api/public/courses/{$course->slug}/lessons/{$lesson->id}/video")
            ->assertNotFound();
    }

    public function test_main_admin_can_access_sections_for_any_course(): void
    {
        $admin = User::factory()->create([
            'username' => 'mainadmin',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $teacher = User::factory()->create([
            'username' => 'teacherone',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $course = $this->makeCourse($teacher, 'Teacher Course');

        $this->actingAs($admin)
            ->getJson("/api/courses/{$course->id}/sections")
            ->assertOk();
    }
}