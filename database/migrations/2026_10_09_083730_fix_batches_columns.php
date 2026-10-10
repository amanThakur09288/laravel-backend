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
    Schema::table('batches', function (Blueprint $table) {
        if (Schema::hasColumn('batches', 'class_timing')) {
            $table->dropColumn('class_timing');
        }

        $table->text('short_description')->nullable()->change();
        $table->text('course_description')->nullable()->change();
        $table->text('batch_overview')->nullable()->change();
        $table->string('location', 500)->nullable()->change();
        $table->text('offer_text')->nullable()->change();
        $table->string('instructor_name')->nullable()->change();
        $table->string('instructor_designation')->nullable()->change();
        $table->string('instructor_experience')->nullable()->change();
        $table->text('instructor_bio')->nullable()->change();
        $table->text('live_class_info')->nullable()->change();
        $table->text('recorded_class_info')->nullable()->change();
        $table->text('doubt_support')->nullable()->change();
        $table->decimal('fee', 10, 2)->change();
        $table->decimal('discounted_fee', 10, 2)->nullable()->change();
    });
}

    /**
     * Reverse the migrations.
     */
   public function down(): void {}
};
