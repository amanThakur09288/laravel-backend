<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('type');
            $table->string('video_original_name')->nullable()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->dropColumn([
                'video_path',
                'video_original_name',
            ]);
        });
    }
};