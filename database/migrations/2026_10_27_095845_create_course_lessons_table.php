<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_section_id')
                ->constrained('course_sections')
                ->cascadeOnDelete();

            $table->string('title');

            $table->text('description')->nullable();

            $table->enum('type', [
                'video',
            ])->default('video');

            $table->unsignedInteger('sort_order');

            $table->unsignedInteger('duration_seconds')
                ->nullable();

            $table->boolean('is_preview')
                ->default(false);

            $table->enum('status', [
                'draft',
                'published',
            ])->default('draft');

            $table->timestamps();

            $table->index([
                'course_section_id',
                'sort_order',
            ]);

            $table->index([
                'course_section_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_lessons');
    }
};