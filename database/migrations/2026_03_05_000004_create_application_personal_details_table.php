<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_personal_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('father_name');
            $table->string('father_cnic', 15);
            $table->string('father_phone', 20);
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relationship')->nullable();
            $table->string('guardian_income')->nullable();
            $table->enum('gender', ['male', 'female', 'transgender']);
            $table->date('date_of_birth');
            $table->string('nationality');
            $table->string('religion');
            $table->string('mother_tongue')->nullable();
            $table->text('postal_address');
            $table->text('permanent_address');
            $table->boolean('same_address')->default(false);
            $table->date('cnic_issuance_date')->nullable();
            $table->string('phone_landline', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_personal_details');
    }
};
