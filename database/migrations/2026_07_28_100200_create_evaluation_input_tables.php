<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_period_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('circle_id')->constrained('circles')->restrictOnDelete();
            $table->decimal('pages_completed', 8, 2)->default(0);
            $table->decimal('revision_pages', 8, 2)->default(0);
            $table->decimal('target_pages_snapshot', 8, 2)->nullable();
            $table->boolean('below_minimum')->default(false);
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('draft');
            $table->foreignId('assessed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['evaluation_candidate_id', 'evaluation_period_id', 'circle_id'],
                'quran_candidate_period_circle_unique'
            );
            $table->index(['evaluation_period_id', 'status']);
        });

        Schema::create('teacher_period_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('circle_id')->constrained('circles')->restrictOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->decimal('behavior_score', 5, 2)->default(0);
            $table->decimal('participation_score', 5, 2)->default(0);
            $table->decimal('teacher_opinion_score', 5, 2)->default(0);
            $table->decimal('total_score', 5, 2)->default(0);
            $table->json('evidence')->nullable();
            $table->text('comments')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['evaluation_candidate_id', 'evaluation_period_id', 'circle_id'],
                'teacher_evaluation_candidate_period_circle_unique'
            );
            $table->index(['evaluation_period_id', 'status']);
        });

        Schema::create('administration_behavior_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_candidate_id');
            $table->foreignId('evaluation_period_id')->nullable();
            $table->string('context_type', 40)->default('general');
            $table->text('description');
            $table->decimal('deduction_points', 5, 2);
            $table->dateTime('occurred_at');
            $table->foreignId('observed_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->foreign(
                'evaluation_candidate_id',
                'admin_behavior_candidate_fk'
            )->references('id')->on('evaluation_candidates')->cascadeOnDelete();
            $table->foreign(
                'evaluation_period_id',
                'admin_behavior_period_fk'
            )->references('id')->on('evaluation_periods')->nullOnDelete();
            $table->index(['evaluation_candidate_id', 'status', 'occurred_at'], 'admin_observations_candidate_index');
        });

        Schema::create('evaluation_exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->decimal('score', 7, 2);
            $table->decimal('maximum_score', 7, 2)->default(100);
            $table->decimal('weight', 7, 3)->default(1);
            $table->string('status', 24)->default('draft');
            $table->foreignId('graded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['evaluation_candidate_id', 'evaluation_period_id', 'course_id', 'subject_id'],
                'evaluation_exam_candidate_subject_unique'
            );
            $table->index(['evaluation_candidate_id', 'status']);
        });

        Schema::create('sabr_part_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('sabr_id')->nullable()->constrained('sabrs')->nullOnDelete();
            $table->foreignId('evaluation_candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('part_number');
            $table->string('source_type', 24);
            $table->decimal('bonus_points', 6, 2);
            $table->dateTime('first_success_at');
            $table->string('evidence_reference')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'part_number'], 'sabr_student_part_once_unique');
            $table->index(['evaluation_candidate_id', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sabr_part_achievements');
        Schema::dropIfExists('evaluation_exam_results');
        Schema::dropIfExists('administration_behavior_observations');
        Schema::dropIfExists('teacher_period_evaluations');
        Schema::dropIfExists('quran_period_assessments');
    }
};
