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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();

            $table->string('course_name')->unique();

            $table->string('category');

            $table->enum('level', [
                'Beginner',
                'Intermediate',
                'Advanced',
            ]);

            $table->text('short_description')->nullable();

            $table->longText('course_description')->nullable();

            $table->string('duration')->nullable();

            $table->unsignedInteger('total_hours')->nullable();

            $table->json('learning_outcomes')->nullable();

            $table->json('curriculum')->nullable();

            $table->json('prerequisites')->nullable();

            $table->json('who_should_join')->nullable();

            $table->json('course_features')->nullable();

            $table->json('faqs')->nullable();

            $table->enum('status', [
                'Active',
                'Inactive',
            ])->default('Active');

            $table->timestamps();

            $table->index('category');
            $table->index('level');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};