<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Promote the first existing admin account to Super Admin.
        $superAdmin = DB::table('users')
            ->where('role', 'admin')
            ->orderBy('id')
            ->first();

        if ($superAdmin) {
            DB::table('users')
                ->where('id', $superAdmin->id)
                ->update(['role' => 'super_admin']);
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('owner_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->restrictOnDelete();
        });

        // Assign existing courses to the promoted Super Admin.
        if ($superAdmin) {
            DB::table('courses')->update([
                'owner_id' => $superAdmin->id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });

        // Do not automatically demote the Super Admin on rollback.
    }
};