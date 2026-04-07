<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['file', 'link']);
            $table->string('file_path', 500)->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['class_id', 'subject_id']);
            $table->index(['class_id', 'deleted_at', 'created_at']);
        });

        // Enforce file/link mutual exclusion at the DB layer so no code path
        // (seeder, future endpoint, buggy refactor) can insert an invalid row.
        // SQLite does not support ALTER TABLE ADD CONSTRAINT, so this only
        // runs on MySQL. For tests (SQLite), StoreClassMaterialRequest + the
        // service layer enforce the same invariant.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE class_materials ADD CONSTRAINT chk_material_type CHECK (
                    (type = 'file' AND file_path IS NOT NULL AND external_url IS NULL)
                    OR
                    (type = 'link' AND external_url IS NOT NULL AND file_path IS NULL)
                )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_materials');
    }
};
