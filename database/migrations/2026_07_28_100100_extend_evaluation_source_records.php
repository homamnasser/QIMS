<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_dates', function (Blueprint $table) {
            $table->foreignId('evaluation_period_id')
                ->nullable()
                ->after('course_id')
                ->constrained('evaluation_periods')
                ->nullOnDelete();
            $table->string('activity_type', 40)->default('general')->after('session_date');
            $table->string('status', 24)->default('scheduled')->after('activity_type');
            $table->boolean('counts_for_attendance')->default(true)->after('status');
            $table->boolean('counts_for_recitation')->default(false)->after('counts_for_attendance');
            $table->timestamp('held_at')->nullable()->after('counts_for_recitation');
            $table->text('cancellation_reason')->nullable()->after('held_at');

            $table->index(['course_id', 'session_date', 'status'], 'course_dates_evaluation_index');
            $table->index(['evaluation_period_id', 'activity_type'], 'course_dates_period_activity_index');
        });

        Schema::table('student_course_absences', function (Blueprint $table) {
            $table->foreignId('course_date_id')
                ->nullable()
                ->after('course')
                ->constrained('course_dates')
                ->nullOnDelete();
            $table->foreignId('circle_id')
                ->nullable()
                ->after('course_date_id')
                ->constrained('circles')
                ->nullOnDelete();
            $table->boolean('is_excused')->default(false)->after('type');
            $table->string('source', 40)->default('legacy')->after('is_excused');
            $table->string('external_reference')->nullable()->after('source');
            $table->timestamp('captured_at')->nullable()->after('external_reference');
            $table->foreignId('recorded_by')
                ->nullable()
                ->after('captured_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->unique(['course_date_id', 'student'], 'attendance_course_date_student_unique');
            $table->unique(['source', 'external_reference'], 'attendance_external_source_unique');
            $table->index(['student', 'date', 'type'], 'attendance_student_date_type_index');
        });

        Schema::table('reading_improvements', function (Blueprint $table) {
            $table->foreignId('evaluation_candidate_id')
                ->nullable()
                ->after('course')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('evaluation_period_id')
                ->nullable()
                ->after('evaluation_candidate_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('evaluator_id')
                ->nullable()
                ->after('evaluation_period_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->decimal('baseline_score', 6, 2)->nullable()->after('type');
            $table->decimal('final_score', 6, 2)->nullable()->after('baseline_score');
            $table->string('baseline_level', 40)->nullable()->after('final_score');
            $table->string('final_level', 40)->nullable()->after('baseline_level');
            $table->decimal('difference', 6, 2)->nullable()->after('final_level');
            $table->smallInteger('points')->nullable()->after('difference');
            $table->boolean('promotion_recommended')->default(false)->after('points');
            $table->string('status', 24)->default('draft')->after('promotion_recommended');
            $table->json('rule_trace')->nullable()->after('status');

            $table->index(
                ['evaluation_candidate_id', 'evaluation_period_id', 'status'],
                'reading_improvements_candidate_period_index'
            );
            $table->unique(
                ['evaluation_candidate_id', 'evaluation_period_id', 'course'],
                'reading_candidate_period_course_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('reading_improvements', function (Blueprint $table) {
            $table->dropIndex('reading_improvements_candidate_period_index');
            $table->dropUnique('reading_candidate_period_course_unique');
            $table->dropConstrainedForeignId('evaluator_id');
            $table->dropConstrainedForeignId('evaluation_period_id');
            $table->dropConstrainedForeignId('evaluation_candidate_id');
            $table->dropColumn([
                'baseline_score',
                'final_score',
                'baseline_level',
                'final_level',
                'difference',
                'points',
                'promotion_recommended',
                'status',
                'rule_trace',
            ]);
        });

        Schema::table('student_course_absences', function (Blueprint $table) {
            $table->dropUnique('attendance_course_date_student_unique');
            $table->dropUnique('attendance_external_source_unique');
            $table->dropIndex('attendance_student_date_type_index');
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropConstrainedForeignId('circle_id');
            $table->dropConstrainedForeignId('course_date_id');
            $table->dropColumn([
                'is_excused',
                'source',
                'external_reference',
                'captured_at',
            ]);
        });

        Schema::table('course_dates', function (Blueprint $table) {
            $table->dropIndex('course_dates_evaluation_index');
            $table->dropIndex('course_dates_period_activity_index');
            $table->dropConstrainedForeignId('evaluation_period_id');
            $table->dropColumn([
                'activity_type',
                'status',
                'counts_for_attendance',
                'counts_for_recitation',
                'held_at',
                'cancellation_reason',
            ]);
        });
    }
};
