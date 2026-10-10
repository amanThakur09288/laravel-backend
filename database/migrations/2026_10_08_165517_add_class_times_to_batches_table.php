<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::table('batches', function (Blueprint $table) {
        if (! Schema::hasColumn('batches', 'class_start_time')) {
            $table->time('class_start_time')->nullable();
        }

        if (! Schema::hasColumn('batches', 'class_end_time')) {
            $table->time('class_end_time')->nullable();
        }
    });
}

public function down(): void
{
}
};
