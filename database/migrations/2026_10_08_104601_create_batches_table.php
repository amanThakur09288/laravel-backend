<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();

            $table->string('slug')->unique();

            $table->string('batch_name');
            $table->string('course_name');

            $table->text('short_description');
            $table->longText('course_description')->nullable();
            $table->longText('batch_overview')->nullable();

            $table->date('start_date');
            $table->date('end_date');

            $table->string('duration_text');
            $table->string('class_days');
            $table->time('class_start_time');
            $table->time('class_end_time');
            $table->unsignedInteger('total_classes');

            $table->enum('mode', [
                'Online',
                'Offline',
                'Hybrid',
            ]);

            $table->string('location')->nullable();

            $table->unsignedInteger('total_seats');
            $table->unsignedInteger('available_seats');

            $table->enum('status', [
                'Enrolling Open',
                'Filling Fast',
                'Few Seats Left',
                'Coming Soon',
                'Sold Out',
            ]);

            $table->decimal('fee', 10, 2);
            $table->decimal('discounted_fee', 10, 2)->nullable();

            $table->text('offer_text')->nullable();

            $table->string('instructor_name')->nullable();
            $table->string('instructor_designation')->nullable();
            $table->string('instructor_experience')->nullable();
            $table->text('instructor_bio')->nullable();

            $table->json('learning_outcomes')->nullable();
            $table->json('curriculum')->nullable();
            $table->json('prerequisites')->nullable();
            $table->json('who_should_join')->nullable();
            $table->json('faqs')->nullable();
            $table->json('payment_info')->nullable();

            $table->text('live_class_info')->nullable();
            $table->text('recorded_class_info')->nullable();
            $table->text('doubt_support')->nullable();

            $table->timestamps();

            $table->index('start_date');
            $table->index('status');
            $table->index('mode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};