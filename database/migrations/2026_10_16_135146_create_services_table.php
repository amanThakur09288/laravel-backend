<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            // Basic information
            $table->string('service_name');
            $table->string('slug')->unique();

            $table->text('short_description');
            $table->longText('description');

            // What We Offer
            $table->json('offerings')->nullable();

            // Our Process
            $table->json('process')->nullable();

            // Frequently Asked Questions
            $table->json('faqs')->nullable();

            // Public visibility
            $table->enum('status', [
                'Active',
                'Inactive',
            ])->default('Active');

            $table->timestamps();

            // Faster filtering by status
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};