<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('policy_id')->constrained('evaluation_policies')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status', 24)->default('running');
            $table->boolean('is_preview')->default(true);
            $table->json('policy_snapshot');
            $table->json('readiness_snapshot')->nullable();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_cycle_id', 'sequence']);
            $table->index(['evaluation_cycle_id', 'status', 'is_preview']);
        });

        Schema::create('evaluation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_candidate_id')->constrained()->cascadeOnDelete();
            $table->decimal('base_score', 9, 2)->default(0);
            $table->decimal('base_maximum', 9, 2)->default(0);
            $table->decimal('bonus_score', 9, 2)->default(0);
            $table->decimal('final_score', 9, 2)->default(0);
            $table->decimal('final_percentage', 7, 2)->default(0);
            $table->boolean('is_excellent')->default(false);
            $table->json('excellence_checks');
            $table->unsignedSmallInteger('rank')->nullable();
            $table->string('status', 24)->default('calculated');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_run_id', 'evaluation_candidate_id'], 'evaluation_run_candidate_unique');
            $table->index(['evaluation_run_id', 'is_excellent', 'rank']);
            $table->index(
                ['evaluation_candidate_id', 'status', 'published_at'],
                'evaluation_result_candidate_status_index'
            );
        });

        Schema::create('evaluation_criterion_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_result_id')->constrained()->cascadeOnDelete();
            $table->string('criterion_key', 40);
            $table->string('criterion_name');
            $table->boolean('is_applicable')->default(true);
            $table->decimal('score', 9, 2)->default(0);
            $table->decimal('maximum_score', 9, 2)->default(0);
            $table->json('inputs');
            $table->json('rule_trace');
            $table->string('readiness_status', 24)->default('ready');
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->unique(
                ['evaluation_result_id', 'criterion_key'],
                'evaluation_result_criterion_unique'
            );
        });

        Schema::create('recognition_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_run_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('status', 24)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_cycle_id', 'evaluation_run_id']);
        });

        Schema::create('recognition_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recognition_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_result_id')->constrained()->cascadeOnDelete();
            $table->string('award_type', 40);
            $table->string('reward_tier', 40)->nullable();
            $table->boolean('receives_material_gift')->default(true);
            $table->string('suppression_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['recognition_batch_id', 'evaluation_result_id', 'award_type'],
                'recognition_result_award_unique'
            );
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_result_id')->constrained()->restrictOnDelete();
            $table->string('certificate_type', 40)->default('final_result');
            $table->string('serial_number')->unique();
            $table->string('verification_token_hash', 64)->unique();
            $table->string('file_disk', 40)->default('local');
            $table->string('file_path');
            $table->string('file_sha256', 64);
            $table->json('data_snapshot');
            $table->string('status', 24)->default('issued');
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_result_id', 'certificate_type', 'version'], 'result_certificate_version_unique');
            $table->index(['evaluation_result_id', 'status']);
        });

        Schema::create('evaluation_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 30)->default('user');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['auditable_type', 'auditable_id', 'occurred_at'], 'evaluation_auditable_index');
            $table->index(['evaluation_cycle_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_audit_events');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('recognition_awards');
        Schema::dropIfExists('recognition_batches');
        Schema::dropIfExists('evaluation_criterion_results');
        Schema::dropIfExists('evaluation_results');
        Schema::dropIfExists('evaluation_runs');
    }
};
