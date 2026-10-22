<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Disable foreign key checks
        |--------------------------------------------------------------------------
        |
        | We are replacing the old Quiz database structure.
        | Some old Quiz tables may still reference each other.
        |
        */

        Schema::disableForeignKeyConstraints();

        /*
        |--------------------------------------------------------------------------
        | Remove old Quiz tables
        |--------------------------------------------------------------------------
        */

        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_attempt_questions');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_options');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('quizzes');

        /*
        |--------------------------------------------------------------------------
        | Enable foreign key checks again
        |--------------------------------------------------------------------------
        */

        Schema::enableForeignKeyConstraints();

        /*
        |--------------------------------------------------------------------------
        | quizzes
        |--------------------------------------------------------------------------
        */

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('batch_id')
                ->constrained('batches')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->string('title');

            $table->string('slug')
                ->unique();

            $table->text('description')
                ->nullable();

            $table->unsignedSmallInteger('duration_minutes');

            $table->unsignedInteger('total_marks');

            $table->unsignedInteger('passing_marks');

            $table->unsignedSmallInteger('attempts_allowed')
                ->default(1);

            $table->boolean('shuffle_questions')
                ->default(false);

            $table->boolean('shuffle_options')
                ->default(false);

            $table->boolean('auto_submit')
                ->default(true);

            $table->boolean('show_result')
                ->default(true);

            $table->boolean('show_correct_answers')
                ->default(true);

            $table->enum('status', [
                'draft',
                'published',
                'closed',
            ])->default('draft');

            $table->timestamps();

            $table->index([
                'batch_id',
                'status',
            ]);

            $table->index('created_by');
        });

        /*
        |--------------------------------------------------------------------------
        | quiz_questions
        |--------------------------------------------------------------------------
        */

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quiz_id')
                ->constrained('quizzes')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->text('question_text');

            $table->enum('type', [
                'single_select',
                'multi_select',
            ]);

            $table->unsignedInteger('marks');

            $table->text('explanation')
                ->nullable();

            $table->unsignedInteger('sort_order');

            $table->timestamps();

            $table->index([
                'quiz_id',
                'sort_order',
            ]);

            $table->unique([
                'quiz_id',
                'sort_order',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | quiz_options
        |--------------------------------------------------------------------------
        */

        Schema::create('quiz_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quiz_question_id')
                ->constrained('quiz_questions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('option_text');

            $table->boolean('is_correct')
                ->default(false);

            $table->unsignedInteger('sort_order');

            $table->timestamps();

            $table->index([
                'quiz_question_id',
                'sort_order',
            ]);

            $table->unique([
                'quiz_question_id',
                'sort_order',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | quiz_attempts
        |--------------------------------------------------------------------------
        */

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quiz_id')
                ->constrained('quizzes')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Nullable because of MySQL timestamp restrictions.
            |--------------------------------------------------------------------------
            */

            $table->timestamp('started_at')
                ->nullable();

            $table->timestamp('expires_at')
                ->nullable();

            $table->timestamp('submitted_at')
                ->nullable();

            $table->enum('status', [
                'in_progress',
                'submitted',
                'expired',
            ])->default('in_progress');

            $table->decimal('score', 8, 2)
                ->nullable();

            $table->unsignedInteger('correct_answers')
                ->default(0);

            $table->unsignedInteger('wrong_answers')
                ->default(0);

            $table->unsignedInteger('unanswered')
                ->default(0);

            $table->timestamps();

            $table->index([
                'quiz_id',
                'user_id',
                'status',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | quiz_answers
        |--------------------------------------------------------------------------
        */

        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attempt_id')
                ->constrained('quiz_attempts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('quiz_question_id')
                ->constrained('quiz_questions')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->json('selected_option_ids')
                ->nullable();

            $table->boolean('is_correct')
                ->nullable();

            $table->decimal('marks_awarded', 8, 2)
                ->default(0);

            $table->timestamp('answered_at')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'attempt_id',
                'quiz_question_id',
            ]);

            $table->index('attempt_id');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_options');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('quizzes');

        Schema::enableForeignKeyConstraints();
    }
};