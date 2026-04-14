<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->foreignId('student_id')->change()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->foreignId('student_id')->change()->constrained('users')->cascadeOnDelete();
        });
    }
};
