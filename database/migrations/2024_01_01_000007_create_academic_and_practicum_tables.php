<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('employee_number')->unique(); // NIDN / NIP
            $table->string('academic_status')->default('active');
            $table->timestamps();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('class_name');
            $table->foreignId('lecturer_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->string('academic_year')->nullable(); // e.g. 2026/2027
            $table->string('semester')->nullable(); // Ganjil / Genap
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('student_number')->unique(); // NIM
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('academic_status')->default('active');
            $table->timestamps();
        });

        Schema::create('practicum_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('group_name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('practicum_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('practicum_groups')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unique(['group_id', 'student_id']);
            $table->timestamps();
        });

        Schema::create('practicums', function (Blueprint $table) {
            $table->id();
            $table->string('practicum_name');
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('practicum_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practicum_id')->constrained('practicums')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('practicum_groups')->cascadeOnDelete();
            $table->string('session_name');
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->decimal('virtual_initial_balance', 15, 2)->default(10000000.00); // Rp 10.000.000 virtual balance
            $table->string('status')->default('OPEN'); // OPEN, CLOSED, ARCHIVED
            $table->timestamps();
        });

        Schema::create('practicum_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('practicum_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('practicum_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('practicum_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('activity_type');
            $table->text('description')->nullable();
            $table->string('reference_id')->nullable();
            $table->timestamps();
        });

        Schema::create('practicum_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('practicum_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->decimal('score', 5, 2);
            $table->text('feedback')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practicum_grades');
        Schema::dropIfExists('practicum_activities');
        Schema::dropIfExists('practicum_assignments');
        Schema::dropIfExists('practicum_sessions');
        Schema::dropIfExists('practicums');
        Schema::dropIfExists('practicum_group_members');
        Schema::dropIfExists('practicum_groups');
        Schema::dropIfExists('students');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('lecturers');
    }
};

