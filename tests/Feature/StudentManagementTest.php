<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'username' => 'mainadmin',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function student(string $username = 'studentone'): User
    {
        return User::factory()->create([
            'username' => $username,
            'role' => User::ROLE_USER,
            'is_active' => true,
        ]);
    }

    public function test_main_admin_can_list_students_without_exposing_staff_accounts(): void
    {
        $admin = $this->admin();
        $student = $this->student();

        User::factory()->create([
            'username' => 'teacherone',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $student->id)
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_student_registration_cannot_assign_a_staff_role(): void
    {
        $this->withSession([])
            ->postJson('/api/student/register', [
                'name' => 'New Student',
                'username' => 'newstudent',
                'email' => 'newstudent@example.com',
                'password' => 'StudentPass123',
                'password_confirmation' => 'StudentPass123',
                'role' => User::ROLE_ADMIN,
            ])
            ->assertCreated()
            ->assertJsonPath('data.user.role', User::ROLE_USER);

        $this->assertDatabaseHas('users', [
            'username' => 'newstudent',
            'role' => User::ROLE_USER,
        ]);
    }

    public function test_teacher_cannot_manage_students(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacherone',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->getJson('/api/students')
            ->assertForbidden();
    }

    public function test_main_admin_can_update_student_but_cannot_change_student_role(): void
    {
        $admin = $this->admin();
        $student = $this->student();

        $this->actingAs($admin)
            ->putJson("/api/students/{$student->id}", [
                'name' => 'Updated Student',
                'role' => User::ROLE_ADMIN,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Student')
            ->assertJsonPath('data.role', User::ROLE_USER);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'role' => User::ROLE_USER,
            'name' => 'Updated Student',
        ]);
    }

    public function test_deactivated_student_cannot_log_in(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $student->update(['password' => 'StudentPass123']);

        $this->actingAs($admin)
            ->patchJson("/api/students/{$student->id}/status", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->withSession([])
            ->postJson('/api/student/login', [
                'username' => $student->username,
                'password' => 'StudentPass123',
            ])
            ->assertUnprocessable();
    }

    public function test_deactivated_student_cannot_use_authenticated_student_routes(): void
    {
        $admin = $this->admin();
        $student = $this->student();

        $this->actingAs($admin)
            ->patchJson("/api/students/{$student->id}/status", [
                'is_active' => false,
            ])
            ->assertOk();

        $this->actingAs($student)
            ->getJson('/api/student/user')
            ->assertForbidden();
    }

    public function test_main_admin_can_delete_student_without_quiz_history(): void
    {
        $admin = $this->admin();
        $student = $this->student();

        $this->actingAs($admin)
            ->deleteJson("/api/students/{$student->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', [
            'id' => $student->id,
        ]);
    }

    public function test_student_with_quiz_history_cannot_be_permanently_deleted(): void
    {
        $admin = $this->admin();
        $student = $this->student();

        $courseOwner = User::factory()->create([
            'username' => 'teacherone',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $course = Course::create([
            'course_name' => 'Quiz Course',
            'slug' => 'quiz-course',
            'category' => 'Networking',
            'level' => 'Beginner',
            'status' => 'Active',
        ]);

        $course->owner_id = $courseOwner->id;
        $course->save();

        $batch = Batch::create([
            'course_id' => $course->id,
            'slug' => 'quiz-batch',
            'batch_name' => 'Quiz Batch',
            'course_name' => $course->course_name,
            'short_description' => 'Test batch',
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-01',
            'duration_text' => '1 month',
            'class_days' => 'Mon',
            'class_start_time' => '09:00:00',
            'class_end_time' => '10:00:00',
            'total_classes' => 4,
            'mode' => 'Online',
            'total_seats' => 10,
            'available_seats' => 10,
            'status' => 'Coming Soon',
            'fee' => 100,
        ]);

        $quiz = Quiz::create([
            'batch_id' => $batch->id,
            'created_by' => $courseOwner->id,
            'title' => 'Test Quiz',
            'slug' => 'test-quiz',
            'duration_minutes' => 10,
            'total_marks' => 10,
            'passing_marks' => 5,
            'attempts_allowed' => 1,
            'status' => 'published',
        ]);

        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'started_at' => now(),
            'status' => 'submitted',
            'score' => 8,
            'correct_answers' => 8,
            'wrong_answers' => 2,
            'unanswered' => 0,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/students/{$student->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
        ]);
    }
}