<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Add slug column
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasColumn('courses', 'slug')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('slug', 191)
                    ->nullable()
                    ->unique()
                    ->after('course_name');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Generate slugs for existing courses
        |--------------------------------------------------------------------------
        */

        $courses = DB::table('courses')
            ->whereNull('slug')
            ->orderBy('id')
            ->get([
                'id',
                'course_name',
            ]);

        foreach ($courses as $course) {
            $baseSlug = Str::slug($course->course_name);

            if ($baseSlug === '') {
                $baseSlug = 'course';
            }

            $slug = $baseSlug;
            $counter = 2;

            /*
            |--------------------------------------------------------------------------
            | Make sure slug is unique
            |--------------------------------------------------------------------------
            */

            while (
                DB::table('courses')
                    ->where('slug', $slug)
                    ->exists()
            ) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            /*
            |--------------------------------------------------------------------------
            | Save slug
            |--------------------------------------------------------------------------
            */

            DB::table('courses')
                ->where('id', $course->id)
                ->update([
                    'slug' => $slug,
                ]);
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        if (Schema::hasColumn('courses', 'slug')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropUnique('courses_slug_unique');
                $table->dropColumn('slug');
            });
        }
    }
};