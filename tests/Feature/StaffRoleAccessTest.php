<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_admin_can_access_main_admin_only_management_routes(): void
    {
        $admin = User::factory()->create([
            'username' => 'mainadmin',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->getJson('/api/admin-users')->assertOk();
        $this->actingAs($admin)->getJson('/api/students')->assertOk();
        $this->actingAs($admin)->getJson('/api/contact-messages')->assertOk();
        $this->actingAs($admin)->getJson('/api/products')->assertOk();
        $this->actingAs($admin)->getJson('/api/services')->assertOk();
    }

    public function test_teacher_cannot_access_main_admin_only_management_routes(): void
    {
        $teacher = User::factory()->create([
            'username' => 'teacherone',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $this->actingAs($teacher)->getJson('/api/admin-users')->assertForbidden();
        $this->actingAs($teacher)->getJson('/api/students')->assertForbidden();
        $this->actingAs($teacher)->getJson('/api/contact-messages')->assertForbidden();
        $this->actingAs($teacher)->getJson('/api/products')->assertForbidden();
        $this->actingAs($teacher)->getJson('/api/services')->assertForbidden();
    }

    public function test_inactive_teacher_cannot_use_admin_api_even_with_an_existing_session(): void
    {
        $teacher = User::factory()->create([
            'username' => 'inactive_teacher',
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
        ]);

        $teacher->is_active = false;
        $teacher->save();

        $this->actingAs($teacher)
            ->getJson('/api/user')
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive.');
    }

    public function test_student_cannot_access_admin_panel_identity_or_management_routes(): void
    {
        $student = User::factory()->create([
            'username' => 'studentone',
            'role' => User::ROLE_USER,
            'is_active' => true,
        ]);

        $this->actingAs($student)->getJson('/api/user')->assertForbidden();
        $this->actingAs($student)->getJson('/api/students')->assertForbidden();
        $this->actingAs($student)->getJson('/api/admin-users')->assertForbidden();
        $this->actingAs($student)->getJson('/api/student/user')->assertOk();
    }
}