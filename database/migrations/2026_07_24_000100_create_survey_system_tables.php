<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('public_token', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('allow_multiple_responses')->default(false);
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('survey_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['survey_id', 'display_order']);
        });

        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('question_key')->unique();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('survey_sections')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->json('validation_rules')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['survey_id', 'section_id', 'display_order'], 'survey_questions_order_index');
        });

        Schema::create('survey_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('survey_questions')->cascadeOnDelete();
            $table->string('label');
            $table->string('value');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['question_id', 'value']);
        });

        Schema::create('survey_student_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('field_key', 80);
            $table->string('label');
            $table->string('mode', 20)->default('display');
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['survey_id', 'field_key']);
        });

        Schema::create('survey_logic_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignId('source_question_id')->constrained('survey_questions')->cascadeOnDelete();
            $table->string('target_type', 20);
            $table->foreignId('target_question_id')->nullable()->constrained('survey_questions')->cascadeOnDelete();
            $table->foreignId('target_section_id')->nullable()->constrained('survey_sections')->cascadeOnDelete();
            $table->string('action', 20);
            $table->string('operator', 20)->default('contains');
            $table->json('values');
            $table->timestamps();
            $table->index(['survey_id', 'source_question_id']);
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('response_token')->unique();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('student_selfnumber_snapshot', 40);
            $table->json('survey_snapshot');
            $table->json('student_data_snapshot');
            $table->string('status', 20)->default('submitted');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index(['survey_id', 'student_id', 'submitted_at'], 'survey_responses_student_index');
        });

        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('survey_responses')->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('survey_questions')->nullOnDelete();
            $table->uuid('question_key');
            $table->string('question_title');
            $table->string('question_type', 40);
            $table->json('value')->nullable();
            $table->timestamps();
            $table->index(['response_id', 'question_key']);
        });

        Schema::create('survey_response_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('survey_responses')->cascadeOnDelete();
            $table->foreignId('answer_id')->constrained('survey_answers')->cascadeOnDelete();
            $table->string('access_token', 64)->unique();
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_response_files');
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_logic_rules');
        Schema::dropIfExists('survey_student_fields');
        Schema::dropIfExists('survey_question_options');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('survey_sections');
        Schema::dropIfExists('surveys');
    }
};
