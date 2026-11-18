
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->foreignId('course_id')
                ->nullable()
                ->after('batch_id')
                ->constrained('courses')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('course_section_id')
                ->nullable()
                ->after('course_id')
                ->constrained('course_sections')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('course_lesson_id')
                ->nullable()
                ->after('course_section_id')
                ->constrained('course_lessons')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->index(
                ['course_id', 'course_section_id', 'course_lesson_id'],
                'quizzes_course_placement_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropIndex('quizzes_course_placement_index');

            $table->dropConstrainedForeignId('course_lesson_id');
            $table->dropConstrainedForeignId('course_section_id');
            $table->dropConstrainedForeignId('course_id');
        });
    }
};
