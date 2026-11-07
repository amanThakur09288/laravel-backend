<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The previous roles were:
        // super_admin = Main Admin, admin = Teacher.
        // Update Teacher first so the following update cannot convert them too.
        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'teacher']);

        DB::table('users')
            ->where('role', 'super_admin')
            ->update(['role' => 'admin']);
    }

    public function down(): void
    {
        // Reverse in the safe order to avoid changing Main Admin into Teacher.
        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'super_admin']);

        DB::table('users')
            ->where('role', 'teacher')
            ->update(['role' => 'admin']);
    }
};