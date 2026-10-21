<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();

            // The quiz being attempted
            $table->foreignId('quiz_id')
                ->constrained('quizzes')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            // The student taking the quiz
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            // Server-controlled quiz timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            // Current attempt state
            $table->enum('status', [
                'in_progress',
                'submitted',
                'expired',
            ])->default('in_progress');

            // Final result
            $table->decimal('score', 8, 2)->nullable();

            $table->unsignedInteger('correct_answers')->default(0);
            $table->unsignedInteger('wrong_answers')->default(0);
            $table->unsignedInteger('unanswered')->default(0);

            $table->timestamps();

            // Useful for finding a student's attempts for a quiz
            $table->index(['quiz_id', 'user_id']);

            // Useful for finding active attempts
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};