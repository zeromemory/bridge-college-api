<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('study_from', ['within_pakistan', 'overseas'])->nullable();
            $table->boolean('prior_computer_knowledge')->default(false);
            $table->boolean('has_computer')->default(false);
            $table->enum('internet_type', ['dsl', 'cable', '3g4g', 'fiber', 'none'])->nullable();
            $table->string('heard_about_us')->nullable();
            $table->boolean('scholarship_interest')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_extras');
    }
};
