<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();

            // The student's quiz attempt
            $table->foreignId('attempt_id')
                ->constrained('quiz_attempts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // The question being answered
            $table->foreignId('question_id')
                ->constrained('questions')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            /*
             * Stores selected option IDs.
             *
             * Single choice example:
             * [3]
             *
             * Multiple choice example:
             * [3, 5]
             */
            $table->json('selected_option_ids')->nullable();

            // Filled only after the server evaluates the answer
            $table->boolean('is_correct')->nullable();

            // Marks awarded by the server
            $table->decimal('marks_awarded', 8, 2)->default(0);

            // When the answer was saved
            $table->timestamp('answered_at')->nullable();

            $table->timestamps();

            // One answer record per question in an attempt
            $table->unique(['attempt_id', 'question_id']);

            $table->index('attempt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }
};