<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();

            $table->text('question_text');

            $table->enum('type', [
                'single_choice',
                'multiple_choice',
            ]);

            $table->unsignedInteger('marks')->default(1);

            // Optional explanation shown after quiz submission
            $table->text('explanation')->nullable();

            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};