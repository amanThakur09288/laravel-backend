<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();

            // Option belongs to a question
            $table->foreignId('question_id')
                ->constrained('questions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // The option text
            $table->text('option_text');

            // Whether this option is a correct answer
            $table->boolean('is_correct')->default(false);

            // Display order
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // Helps retrieve options in the correct order
            $table->index(['question_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};