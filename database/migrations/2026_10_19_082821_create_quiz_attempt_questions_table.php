<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempt_questions', function (Blueprint $table) {
            $table->id();

            // The student's quiz attempt
            $table->foreignId('attempt_id')
                ->constrained('quiz_attempts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // The question assigned to this attempt
            $table->foreignId('question_id')
                ->constrained('questions')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            // Question position for this particular attempt
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // Same question cannot appear twice in one attempt
            $table->unique(['attempt_id', 'question_id']);

            // Quickly retrieve questions in attempt order
            $table->index(['attempt_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_questions');
    }
};