<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('marked_by')->constrained('users')->restrictOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'leave']);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['class_id', 'student_id', 'date']);
            $table->index(['class_id', 'date']);
            $table->index(['student_id', 'class_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
