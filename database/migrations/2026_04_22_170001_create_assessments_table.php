<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->enum('type', [
                'class_test',
                'assignment',
                'monthly_assessment',
                'quarterly_mock_exam',
                'final_exam',
            ]);
            $table->decimal('total_marks', 4, 1);
            $table->date('date');
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // No DB-level unique — SoftDeletes breaks MySQL unique constraints.
            // Enforced in application layer via Rule::unique()->whereNull('deleted_at').

            $table->index(['class_id', 'subject_id', 'is_published']);
            $table->index(['subject_id']);
            $table->index(['teacher_id']);
            $table->index(['date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
