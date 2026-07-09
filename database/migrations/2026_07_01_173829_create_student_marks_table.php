<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_marks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('teacher')->constrained('users')->onDelete('cascade');
            $table->foreignId('student')->constrained('students')->onDelete('cascade');
            $table->foreignId('course')->constrained('courses')->onDelete('cascade');
            $table->foreignId('circle')->constrained('circles')->onDelete('cascade');

            $table->integer('attendance_marks')->default(0);
            $table->integer('memorization_marks')->default(0);
            $table->integer('reading_improvement_marks')->default(0);
            $table->integer('exams_marks')->default(0);
            $table->integer('behavior_teacher_marks')->default(0);
            $table->integer('behavior_admin_marks')->default(0);

            $table->integer('sabr_bonus')->default(0);

            $table->integer('total_marks')->default(0);

            $table->timestamps();

            $table->unique(['student', 'course', 'circle'], 'unique_student_course_circle_marks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_marks');
    }
};
