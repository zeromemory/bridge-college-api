<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->string('grade', 5);
            $table->decimal('min_percentage', 5, 2);
            $table->decimal('max_percentage', 5, 2);
            $table->string('remarks', 50);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_scales');
    }
};
