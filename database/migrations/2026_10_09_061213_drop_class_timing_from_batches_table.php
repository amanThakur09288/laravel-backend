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
        $table->dropColumn('class_timing');
    });
}

public function down(): void
{
    Schema::table('batches', function (Blueprint $table) {
        $table->string('class_timing')->nullable();
    });
}
};
