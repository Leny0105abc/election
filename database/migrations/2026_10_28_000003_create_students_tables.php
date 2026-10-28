<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('student_number', 30)->unique();
            $table->string('lrn', 12)->nullable()->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 10)->nullable();
            // PINs are only ever stored as one-way hashes.
            $table->string('pin_hash')->nullable();
            $table->timestamp('pin_generated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('grade_level');
            $table->string('section', 60);
            $table->string('enrollment_status', 20)->default('enrolled');
            $table->boolean('is_eligible')->default(true);
            $table->timestamps();

            $table->unique(['student_id', 'school_year_id']);
            $table->index(['school_year_id', 'grade_level', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('students');
    }
};
