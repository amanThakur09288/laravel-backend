<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
{
    Schema::table('batches', function (Blueprint $table) {
        $table->time('class_start_time')->nullable();
        $table->time('class_end_time')->nullable();
    });
}

public function down(): void
{
    Schema::table('batches', function (Blueprint $table) {
        $table->dropColumn(['class_start_time', 'class_end_time']);
    });
}
};
