<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    public function test_main_admin_is_staff_but_not_teacher_or_student(): void
    {
        $user = new User(['role' => User::ROLE_ADMIN]);

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isStaff());
        $this->assertFalse($user->isTeacher());
        $this->assertFalse($user->isStudent());
    }

    public function test_teacher_is_staff_but_not_main_admin_or_student(): void
    {
        $user = new User(['role' => User::ROLE_TEACHER]);

        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isStaff());
        $this->assertTrue($user->isTeacher());
        $this->assertFalse($user->isStudent());
    }

    public function test_student_role_remains_separate_from_staff(): void
    {
        $user = new User(['role' => User::ROLE_USER]);

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isStaff());
        $this->assertFalse($user->isTeacher());
        $this->assertTrue($user->isStudent());
    }
}