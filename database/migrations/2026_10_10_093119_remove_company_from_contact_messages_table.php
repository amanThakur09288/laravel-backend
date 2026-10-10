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
    if (Schema::hasColumn('contact_messages', 'company')) {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn('company');
        });
    }
}

public function down(): void
{
    //
}
};
