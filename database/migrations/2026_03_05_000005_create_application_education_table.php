<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_education', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('qualification');
            $table->string('board_university');
            $table->string('roll_no')->nullable();
            $table->string('registration_no')->nullable();
            $table->string('exam_type')->nullable();
            $table->integer('exam_year');
            $table->integer('total_marks');
            $table->integer('obtained_marks');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_education');
    }
};
