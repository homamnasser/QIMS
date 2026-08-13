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
        Schema::create('student_course_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student')->constrained('students')->onDelete('cascade');
            $table->foreignId('course')->constrained('courses')->onDelete('cascade');

            $table->string('note')->nullable();

            $table->enum('type', ['present', 'full', 'first_period', 'second_period']);

            $table->date('date')->nullable();

            $table->unique(['student', 'course', 'date'], 'unique_student_course_date_absence');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_course_absences');
    }
};
