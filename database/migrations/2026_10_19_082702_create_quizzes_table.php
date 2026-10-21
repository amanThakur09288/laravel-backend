<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();

            // Quiz belongs to a batch
            $table->foreignId('batch_id')
                ->constrained('batches')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Admin who created the quiz
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('title');
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            // Quiz timing and marks
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('total_marks')->default(0);
            $table->unsignedInteger('passing_marks')->default(0);

            // Number of attempts allowed per student
            $table->unsignedInteger('attempts_allowed')->default(1);

            // Quiz behavior
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->boolean('auto_submit')->default(true);

            // Result visibility
            $table->boolean('show_result')->default(true);
            $table->boolean('show_correct_answers')->default(true);

            // draft / published / closed
            $table->enum('status', [
                'draft',
                'published',
                'closed',
            ])->default('draft');

            $table->timestamps();

            // Useful indexes
            $table->index(['batch_id', 'status']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};