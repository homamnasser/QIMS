<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status', 24)->default('draft');
            $table->json('configuration');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'version']);
            $table->index(['status', 'approved_at']);
        });

        Schema::create('evaluation_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('policy_id')->constrained('evaluation_policies')->restrictOnDelete();
            $table->string('name');
            $table->string('season', 24)->default('winter');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 24)->default('draft');
            $table->unsignedSmallInteger('top_students_count')->default(15);
            $table->timestamp('data_cutoff_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'name']);
            $table->index(['project_id', 'status', 'start_date', 'end_date']);
        });

        Schema::create('evaluation_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sequence');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 24)->default('draft');
            $table->timestamps();

            $table->unique(['evaluation_cycle_id', 'sequence']);
            $table->index(['evaluation_cycle_id', 'start_date', 'end_date']);
        });

        Schema::create('evaluation_cycle_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['evaluation_cycle_id', 'course_id']);
        });

        Schema::create('evaluation_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('mosque_id')->nullable()->constrained('mosques')->nullOnDelete();
            $table->string('academic_class_snapshot')->nullable();
            $table->string('reading_level_snapshot')->nullable();
            $table->string('status', 24)->default('active');
            $table->text('status_reason')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_cycle_id', 'student_id']);
            $table->index(['evaluation_cycle_id', 'status', 'mosque_id']);
        });

        Schema::create('evaluation_candidate_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('circle_id')->constrained('circles')->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('course_name_snapshot');
            $table->string('circle_name_snapshot');
            $table->string('teacher_name_snapshot')->nullable();
            $table->timestamps();

            $table->unique(
                ['evaluation_candidate_id', 'course_id', 'circle_id'],
                'evaluation_candidate_course_circle_unique'
            );
            $table->index(['course_id', 'circle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_candidate_enrollments');
        Schema::dropIfExists('evaluation_candidates');
        Schema::dropIfExists('evaluation_cycle_courses');
        Schema::dropIfExists('evaluation_periods');
        Schema::dropIfExists('evaluation_cycles');
        Schema::dropIfExists('evaluation_policies');
    }
};
