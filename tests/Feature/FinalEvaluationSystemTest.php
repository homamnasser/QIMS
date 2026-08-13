<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Models\Certificate;
use App\Models\Circle;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\EvaluationCandidate;
use App\Models\EvaluationCandidateEnrollment;
use App\Models\EvaluationCriterionResult;
use App\Models\EvaluationCycle;
use App\Models\EvaluationPeriod;
use App\Models\EvaluationPolicy;
use App\Models\EvaluationResult;
use App\Models\EvaluationRun;
use App\Models\Exam;
use App\Models\Memorization;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\ReadingImprovement;
use App\Models\Role;
use App\Models\Sabr;
use App\Models\Student;
use App\Models\StudentCircle;
use App\Models\StudentCourseAbsence;
use App\Models\Subject;
use App\Models\TeacherPeriodEvaluation;
use App\Models\User;
use App\Models\Warning;
use App\Services\Evaluation\Criteria\AdministrationEvaluationCalculator;
use App\Services\Evaluation\Criteria\AttendanceCalculator;
use App\Services\Evaluation\Criteria\QuranCalculator;
use App\Services\Evaluation\Criteria\ReadingCalculator;
use App\Services\Evaluation\Criteria\SabrBonusCalculator;
use App\Services\Evaluation\Criteria\TeacherEvaluationCalculator;
use App\Services\Evaluation\Criteria\TheoreticalExamCalculator;
use App\Services\Evaluation\EvaluationCalculator;
use App\Services\Evaluation\EvaluationCandidateSyncService;
use App\Services\Evaluation\EvaluationCycleService;
use App\Services\Evaluation\EvaluationInputService;
use App\Services\Evaluation\EvaluationPolicyService;
use App\Services\Evaluation\EvaluationRuleDefinitionService;
use App\Services\Evaluation\EvaluationRuleEngine;
use App\Services\Evaluation\EvaluationRunService;
use App\Services\Evaluation\ExcellenceEvaluator;
use App\Services\Evaluation\RecognitionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinalEvaluationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_rule_template_reproduces_attendance_and_quran_formulas(): void
    {
        $definitions = app(EvaluationRuleDefinitionService::class);
        $engine = app(EvaluationRuleEngine::class);
        $rules = collect($definitions->normalize($definitions->template())['criteria']);

        $attendance = $engine->apply([
            'key' => 'attendance',
            'name' => 'الحضور',
            'is_applicable' => true,
            'score' => 95,
            'maximum_score' => 130,
            'inputs' => ['attendance_percentage' => 95],
            'rule_trace' => [],
        ], $rules->get('attendance'));
        $this->assertSame(110.0, $attendance['score']);
        $this->assertSame('النتيجة الافتراضية', $attendance['rule_trace']['dynamic_rule']['matched_rule_label']);

        $quranBelowTarget = $engine->apply([
            'key' => 'quran',
            'name' => 'القرآن',
            'is_applicable' => true,
            'score' => 0,
            'maximum_score' => 100,
            'inputs' => [
                'pages_completed' => 10,
                'target_pages' => 20,
                'revision_pages' => 0,
                'below_minimum' => false,
            ],
            'rule_trace' => [],
        ], $rules->get('quran'));
        $this->assertSame(0.0, $quranBelowTarget['score']);
        $this->assertSame(
            'quran-target-not-reached',
            $quranBelowTarget['rule_trace']['dynamic_rule']['matched_rule_id']
        );

        $quranAboveTarget = $engine->apply([
            'key' => 'quran',
            'name' => 'القرآن',
            'is_applicable' => true,
            'score' => 0,
            'maximum_score' => 100,
            'inputs' => [
                'pages_completed' => 22,
                'target_pages' => 20,
                'revision_pages' => 0,
                'below_minimum' => false,
            ],
            'rule_trace' => [],
        ], $rules->get('quran'));
        $this->assertSame(72.0, $quranAboveTarget['score']);
    }

    public function test_new_cycle_can_freeze_a_custom_rule_policy(): void
    {
        $context = $this->context();
        $configuration = app(EvaluationRuleDefinitionService::class)->template();
        $configuration['criteria'][0]['rules'] = [];
        $configuration['criteria'][0]['maximum_score'] = 200;
        $configuration['criteria'][0]['default_score'] = [
            'type' => 'fixed',
            'value' => 42,
        ];

        $cycle = app(EvaluationCycleService::class)->create([
            'project_id' => $context['project']->id,
            'name' => 'دورة بقواعد مخصصة',
            'season' => 'winter',
            'course_ids' => [$context['course']->id],
            'periods' => [[
                'name' => 'فترة القواعد',
                'sequence' => 1,
                'start_date' => '2026-02-01',
                'end_date' => '2026-02-28',
            ]],
            'rule_configuration' => $configuration,
        ], $context['supervisor']);

        $this->assertNotSame($context['policy']->id, $cycle->policy_id);
        $this->assertSame(2, $cycle->policy->configuration['schema_version']);
        $this->assertEquals(
            200.0,
            $cycle->policy->configuration['criteria_rules']['criteria']['attendance']['maximum_score']
        );
        $this->assertEquals(
            42.0,
            $cycle->policy->configuration['criteria_rules']['criteria']['attendance']['default_score']['value']
        );
    }

    public function test_custom_cycle_rule_is_applied_by_the_full_evaluation_calculator(): void
    {
        $context = $this->context();
        $definitions = app(EvaluationRuleDefinitionService::class);
        $rules = $definitions->normalize($definitions->template());
        $rules['criteria']['attendance']['rules'] = [];
        $rules['criteria']['attendance']['default_score'] = [
            'type' => 'fixed',
            'value' => 42.0,
        ];
        $policy = config('evaluation.default_policy');
        $policy['criteria_rules'] = $rules;

        $calculation = app(EvaluationCalculator::class)->calculate(
            $context['candidate']->fresh(),
            $policy
        );
        $attendance = collect($calculation['criteria'])->keyBy('key')->get('attendance');

        $this->assertSame(42.0, $attendance['score']);
        $this->assertSame(
            'النتيجة الافتراضية',
            $attendance['rule_trace']['dynamic_rule']['matched_rule_label']
        );
    }

    public function test_cycle_range_is_derived_from_its_evaluation_periods(): void
    {
        $context = $this->context();

        $cycle = app(EvaluationCycleService::class)->create([
            'project_id' => $context['project']->id,
            'name' => 'دورة مشتقة التواريخ',
            'season' => 'winter',
            'course_ids' => [$context['course']->id],
            'periods' => [
                [
                    'name' => 'الفترة الثانية',
                    'sequence' => 2,
                    'start_date' => '2026-04-01',
                    'end_date' => '2026-06-30',
                ],
                [
                    'name' => 'الفترة الأولى',
                    'sequence' => 1,
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-03-31',
                ],
            ],
        ], $context['supervisor']);

        $this->assertSame('2026-01-01', $cycle->start_date->toDateString());
        $this->assertSame('2026-06-30', $cycle->end_date->toDateString());
    }

    public function test_cycle_definition_can_be_corrected_while_data_collection_is_open(): void
    {
        $context = $this->context();
        $configuration = app(EvaluationRuleDefinitionService::class)->template();
        $configuration['criteria'][0]['rules'] = [];
        $configuration['criteria'][0]['default_score'] = ['type' => 'fixed', 'value' => 42];

        $cycle = app(EvaluationCycleService::class)->update($context['cycle'], [
            'name' => 'شتاء 2026 — مصحح',
            'top_students_count' => 20,
            'periods' => [
                [
                    'name' => 'الفترة الأولى المعدلة',
                    'sequence' => 1,
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-01-20',
                ],
                [
                    'name' => 'الفترة الثانية',
                    'sequence' => 2,
                    'start_date' => '2026-01-21',
                    'end_date' => '2026-02-05',
                ],
            ],
            'rule_configuration' => $configuration,
        ], $context['supervisor']);

        $this->assertSame('شتاء 2026 — مصحح', $cycle->name);
        $this->assertSame(20, (int) $cycle->top_students_count);
        $this->assertSame('2026-02-05', $cycle->end_date->toDateString());
        $this->assertSame('الفترة الأولى المعدلة', $cycle->periods->firstWhere('sequence', 1)->name);
        $this->assertSame('2026-01-20', $cycle->periods->firstWhere('sequence', 1)->end_date->toDateString());

        // القواعد تنتقل إلى نسخة سياسة جديدة بدل تعديل النسخة المرتبطة بنتائج محفوظة.
        $this->assertNotSame($context['policy']->id, $cycle->policy_id);
        $this->assertEquals(
            42.0,
            $cycle->policy->configuration['criteria_rules']['criteria']['attendance']['default_score']['value']
        );
        $this->assertDatabaseHas('evaluation_audit_events', [
            'evaluation_cycle_id' => $cycle->id,
            'event_type' => 'evaluation.cycle_updated',
        ]);
    }

    public function test_rule_profile_is_saved_once_and_reused_by_later_cycles(): void
    {
        $context = $this->context();
        $definitions = app(EvaluationRuleDefinitionService::class);
        $policies = app(EvaluationPolicyService::class);
        $configuration = $definitions->template();
        $configuration['criteria'][0]['maximum_score'] = 111;

        $profile = $policies->saveTemplate('قواعد المعهد القياسية', $configuration, $context['supervisor']);

        $this->assertSame('template', $profile->status);
        $this->assertSame(1, $profile->version);
        $this->assertSame(
            ['قواعد المعهد القياسية'],
            $policies->templates()->pluck('name')->all()
        );

        // القالب يعاد إلى شكل المحرر، ثم تنشأ منه دورة جديدة بنسخة سياسة مستقلة.
        $reloaded = $definitions->templateFromRules($profile->configuration['criteria_rules']);
        $cycle = app(EvaluationCycleService::class)->create([
            'project_id' => $context['project']->id,
            'name' => 'دورة من قالب',
            'course_ids' => [$context['course']->id],
            'periods' => [[
                'name' => 'فترة',
                'sequence' => 1,
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
            ]],
            'rule_configuration' => $reloaded,
        ], $context['supervisor']);

        $this->assertNotSame($profile->id, $cycle->policy_id);
        $this->assertSame('approved', $cycle->policy->status);
        $this->assertEquals(
            111.0,
            $cycle->policy->configuration['criteria_rules']['criteria']['attendance']['maximum_score']
        );

        // إعادة الحفظ بالاسم نفسه تنتج نسخة أحدث بدل الاصطدام بقيد التفرد،
        // ولا تظهر في القائمة إلا الأحدث.
        $configuration['criteria'][0]['maximum_score'] = 122;
        $second = $policies->saveTemplate('قواعد المعهد القياسية', $configuration, $context['supervisor']);
        $this->assertSame(2, $second->version);
        $this->assertSame([2], $policies->templates()->pluck('version')->all());
    }

    public function test_saved_cycle_rules_reload_into_the_editor_template_shape(): void
    {
        $context = $this->context();
        $definitions = app(EvaluationRuleDefinitionService::class);
        $configuration = $definitions->template();
        $configuration['criteria'][0]['maximum_score'] = 120;
        $configuration['criteria'][0]['rules'] = [];
        $configuration['criteria'][0]['default_score'] = ['type' => 'fixed', 'value' => 42];

        $cycle = app(EvaluationCycleService::class)->update($context['cycle'], [
            'rule_configuration' => $configuration,
        ], $context['supervisor']);

        $reloaded = $definitions->templateFromRules($cycle->policy->configuration['criteria_rules']);
        $attendance = collect($reloaded['criteria'])->firstWhere('key', 'attendance');

        $this->assertSame([], $attendance['rules']);
        $this->assertEquals(120.0, $attendance['maximum_score']);
        $this->assertEquals(42.0, $attendance['default_score']['value']);
        // المتغيرات تأتي من القالب: السياسة لا تحفظها، والمحرر لا يعمل بدونها.
        $this->assertNotEmpty($attendance['variables']);
        $this->assertSame(
            $definitions->template()['criteria'][0]['variables'],
            $attendance['variables']
        );
    }

    public function test_period_dates_are_locked_once_the_period_has_received_inputs(): void
    {
        $context = $this->context();
        TeacherPeriodEvaluation::create([
            'evaluation_candidate_id' => $context['candidate']->id,
            'evaluation_period_id' => $context['periods'][0]->id,
            'circle_id' => $context['circle']->id,
            'evaluator_id' => $context['teacher']->id,
            'behavior_score' => 20,
            'participation_score' => 20,
            'teacher_opinion_score' => 10,
            'total_score' => 50,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // التسمية وحدها تمر، لأنها لا تغير نافذة أي مدخل.
        $renamed = app(EvaluationCycleService::class)->update($context['cycle'], [
            'periods' => [
                ['name' => 'اسم جديد', 'sequence' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-01-15'],
                ['name' => 'الفترة الثانية', 'sequence' => 2, 'start_date' => '2026-01-16', 'end_date' => '2026-01-31'],
            ],
        ], $context['supervisor']);
        $this->assertSame('اسم جديد', $renamed->periods->firstWhere('sequence', 1)->name);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('بعد إدخال تقييمات مرتبطة بها');

        app(EvaluationCycleService::class)->update($context['cycle']->fresh(), [
            'periods' => [
                ['name' => 'اسم جديد', 'sequence' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-01-10'],
                ['name' => 'الفترة الثانية', 'sequence' => 2, 'start_date' => '2026-01-16', 'end_date' => '2026-01-31'],
            ],
        ], $context['supervisor']);
    }

    public function test_course_removal_is_rejected_after_inputs_and_excludes_orphan_candidates_before_them(): void
    {
        $context = $this->context();
        $other = Course::create([
            'name' => 'مقرر ثانٍ',
            'description' => 'مقرر',
            'mosque_id' => $context['mosque']->id,
            'project_id' => $context['project']->id,
            'supervisor_id' => $context['supervisor']->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_active' => true,
        ]);
        $context['cycle']->courses()->attach($other->id);
        $evaluation = TeacherPeriodEvaluation::create([
            'evaluation_candidate_id' => $context['candidate']->id,
            'evaluation_period_id' => $context['periods'][0]->id,
            'circle_id' => $context['circle']->id,
            'evaluator_id' => $context['teacher']->id,
            'behavior_score' => 20,
            'participation_score' => 20,
            'teacher_opinion_score' => 10,
            'total_score' => 50,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        try {
            app(EvaluationCycleService::class)->update($context['cycle'], [
                'course_ids' => [$other->id],
            ], $context['supervisor']);
            $this->fail('إزالة مقرر له مدخلات يجب أن ترفض.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'بعد إدخال تقييمات لطلابه',
                $exception->validator->errors()->first('course_ids')
            );
        }
        $this->assertCount(2, $context['cycle']->fresh()->courses);

        $evaluation->delete();
        $cycle = app(EvaluationCycleService::class)->update($context['cycle']->fresh(), [
            'course_ids' => [$other->id],
        ], $context['supervisor']);

        $this->assertSame([$other->id], $cycle->courses->pluck('id')->all());
        $this->assertDatabaseMissing('evaluation_candidate_enrollments', [
            'evaluation_candidate_id' => $context['candidate']->id,
            'course_id' => $context['course']->id,
        ]);
        $this->assertSame('excluded', $context['candidate']->fresh()->status);
    }

    public function test_evaluation_data_collection_cannot_start_before_the_last_period_ends(): void
    {
        $context = $this->context();
        $context['periods'][0]->update(['end_date' => '2026-01-05']);
        $context['periods'][1]->update([
            'start_date' => '2026-01-06',
            'end_date' => '2026-01-10',
        ]);
        $context['cycle']->update([
            'status' => 'draft',
            'end_date' => '2026-01-10',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('لا يبدأ التقييم إلا بعد انتهاء آخر فترة');

        app(EvaluationCycleService::class)->transition(
            $context['cycle']->fresh(),
            'data_collection',
            $context['supervisor']
        );
    }

    public function test_attendance_denominator_uses_only_actual_course_dates_inside_periods(): void
    {
        $context = $this->context();
        $eligible = CourseDate::create([
            'course_id' => $context['course']->id,
            'session_date' => '2026-01-02',
            'counts_for_attendance' => true,
        ]);
        $notAnAttendanceDay = CourseDate::create([
            'course_id' => $context['course']->id,
            'session_date' => '2026-01-03',
            'counts_for_attendance' => false,
        ]);
        $outsidePeriods = CourseDate::create([
            'course_id' => $context['course']->id,
            'session_date' => '2026-02-01',
            'counts_for_attendance' => true,
        ]);

        foreach ([$eligible, $notAnAttendanceDay, $outsidePeriods] as $index => $session) {
            StudentCourseAbsence::create([
                'student' => $context['student']->id,
                'course' => $context['course']->id,
                'course_date_id' => $session->id,
                'type' => 'present',
                'source' => 'camera',
                'external_reference' => 'session-scope-'.$index,
                'date' => $session->session_date,
            ]);
        }

        $criterion = app(AttendanceCalculator::class)->calculate(
            $context['candidate']->fresh(['cycle.periods', 'enrollments']),
            config('evaluation.default_policy')
        );

        $this->assertSame(1, $criterion['inputs']['total_sessions']);
        $this->assertSame([$eligible->id], $criterion['rule_trace']['session_ids']);
        $this->assertSame(100.0, $criterion['inputs']['attendance_percentage']);
    }

    public function test_candidate_sync_snapshots_the_circle_quran_mode(): void
    {
        $context = $this->context();
        $context['circle']->update(['quran_mode' => 'talqin']);
        $context['candidate']->delete();
        StudentCircle::create([
            'student' => $context['student']->id,
            'circle' => $context['circle']->id,
        ]);

        app(EvaluationCandidateSyncService::class)->sync($context['cycle']);

        $this->assertDatabaseHas('evaluation_candidate_enrollments', [
            'circle_id' => $context['circle']->id,
            'quran_mode_snapshot' => 'talqin',
        ]);
    }

    public function test_attendance_uses_equivalent_absence_formula_and_nasheea_bonus_curve(): void
    {
        $context = $this->context();
        $types = [
            ['full', false],
            ['full', true],
            ['full', true],
            ...array_fill(0, 6, ['first_period', false]),
            ...array_fill(0, 4, ['second_period', false]),
            ...array_fill(0, 7, ['present', false]),
        ];

        foreach ($types as $index => [$type, $excused]) {
            $date = CarbonImmutable::parse('2026-01-01')->addDays($index);
            $session = CourseDate::create([
                'course_id' => $context['course']->id,
                'session_date' => $date->toDateString(),
                'status' => 'held',
                'counts_for_attendance' => true,
            ]);
            StudentCourseAbsence::create([
                'student' => $context['student']->id,
                'course' => $context['course']->id,
                'course_date_id' => $session->id,
                'circle_id' => $context['circle']->id,
                'type' => $type,
                'is_excused' => $excused,
                'source' => 'camera',
                'external_reference' => 'attendance-'.$index,
                'captured_at' => $date,
                'date' => $date->toDateString(),
            ]);
        }

        $result = app(AttendanceCalculator::class)->calculate(
            $context['candidate']->fresh(['cycle', 'enrollments']),
            config('evaluation.default_policy')
        );

        $this->assertSame(20, $result['inputs']['total_sessions']);
        $this->assertSame(4.0, $result['inputs']['equivalent_absence']);
        $this->assertSame(80.0, $result['inputs']['attendance_percentage']);
        $this->assertSame(80.0, $result['score']);
        $this->assertSame('ready', $result['readiness_status']);
    }

    public function test_perfect_attendance_scores_130_and_missing_camera_log_blocks_readiness(): void
    {
        $context = $this->context();
        foreach (range(0, 9) as $index) {
            $date = CarbonImmutable::parse('2026-01-01')->addDays($index);
            $session = CourseDate::create([
                'course_id' => $context['course']->id,
                'session_date' => $date->toDateString(),
                'status' => 'held',
                'counts_for_attendance' => true,
            ]);
            if ($index < 9) {
                StudentCourseAbsence::create([
                    'student' => $context['student']->id,
                    'course' => $context['course']->id,
                    'course_date_id' => $session->id,
                    'type' => 'present',
                    'is_excused' => false,
                    'source' => 'mobile',
                    'external_reference' => 'mobile-'.$index,
                    'date' => $date->toDateString(),
                ]);
            }
        }

        $calculator = app(AttendanceCalculator::class);
        $candidate = $context['candidate']->fresh(['cycle', 'enrollments']);
        $missing = $calculator->calculate($candidate, config('evaluation.default_policy'));
        $this->assertSame('missing', $missing['readiness_status']);
        $this->assertSame(1, $missing['inputs']['counts']['missing_records']);

        $lastSession = CourseDate::latest('id')->firstOrFail();
        StudentCourseAbsence::create([
            'student' => $context['student']->id,
            'course' => $context['course']->id,
            'course_date_id' => $lastSession->id,
            'type' => 'present',
            'is_excused' => false,
            'source' => 'mobile',
            'external_reference' => 'mobile-9',
            'date' => $lastSession->session_date,
        ]);

        $complete = $calculator->calculate($candidate, config('evaluation.default_policy'));
        $this->assertSame(100.0, $complete['inputs']['attendance_percentage']);
        $this->assertSame(130.0, $complete['score']);
        $this->assertSame('ready', $complete['readiness_status']);
    }

    public function test_attendance_writer_keys_are_idempotent_for_session_and_external_reference(): void
    {
        $context = $this->context();
        $session = CourseDate::create([
            'course_id' => $context['course']->id,
            'session_date' => CarbonImmutable::parse('2026-01-01')->toDateString(),
        ]);
        $base = [
            'student' => $context['student']->id,
            'course' => $context['course']->id,
            'course_date_id' => $session->id,
            'type' => 'present',
            'source' => 'camera',
            'external_reference' => 'face-event-1',
            'date' => $session->session_date,
        ];
        StudentCourseAbsence::create($base);

        $this->expectException(QueryException::class);
        StudentCourseAbsence::create([
            ...$base,
            'external_reference' => 'face-event-2',
        ]);
    }

    public function test_quran_target_is_derived_from_circle_mode_and_requires_manual_below_minimum_flag(): void
    {
        $context = $this->context();
        $context['circle']->update(['quran_mode' => 'recitation']);
        $context['candidate']->enrollments()->update(['quran_mode_snapshot' => 'recitation']);
        foreach (['2026-01-02', '2026-01-03', '2026-01-04', '2026-01-05'] as $date) {
            CourseDate::create([
                'course_id' => $context['course']->id,
                'session_date' => $date,
                'status' => 'held',
                'counts_for_attendance' => true,
            ]);
        }

        $service = app(EvaluationInputService::class);
        foreach ([1, 2] as $page) {
            Memorization::create([
                'student' => $context['student']->id,
                'giver' => $context['teacher']->id,
                'course_id' => $context['course']->id,
                'circle_id' => $context['circle']->id,
                'record_type' => 'memorization',
                'recorded_at' => '2026-01-05 12:00:00',
                'page_number' => $page,
            ]);
        }
        $payload = [
            'evaluation_period_id' => $context['periods'][0]->id,
            'circle_id' => $context['circle']->id,
            'below_minimum' => false,
        ];

        try {
            $service->saveQuran($context['candidate'], $payload, $context['teacher']);
            $this->fail('Expected below-minimum validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('below_minimum', $exception->errors());
        }

        $assessment = $service->saveQuran(
            $context['candidate'],
            [...$payload, 'below_minimum' => true],
            $context['teacher']
        );
        $this->assertEquals(4.0, $assessment->target_pages_snapshot);

        $criterion = app(QuranCalculator::class)->calculate(
            $context['candidate']->fresh(['cycle.periods', 'enrollments']),
            config('evaluation.default_policy')
        );
        $this->assertSame(0.0, $criterion['score']);
        $this->assertTrue($criterion['inputs']['below_minimum']);
    }

    public function test_talqin_circle_targets_half_a_page_for_each_actual_attendance_day(): void
    {
        $context = $this->context();
        $context['circle']->update(['quran_mode' => 'talqin']);
        $context['candidate']->enrollments()->update(['quran_mode_snapshot' => 'talqin']);

        foreach (['2026-01-02', '2026-01-03', '2026-01-04', '2026-01-05'] as $date) {
            CourseDate::create([
                'course_id' => $context['course']->id,
                'session_date' => $date,
                'counts_for_attendance' => true,
            ]);
        }
        foreach ([10, 11] as $page) {
            Memorization::create([
                'student' => $context['student']->id,
                'giver' => $context['teacher']->id,
                'course_id' => $context['course']->id,
                'circle_id' => $context['circle']->id,
                'record_type' => 'memorization',
                'recorded_at' => '2026-01-05 12:00:00',
                'page_number' => $page,
            ]);
        }

        $criterion = app(QuranCalculator::class)->calculate(
            $context['candidate']->fresh(['cycle.periods', 'enrollments']),
            config('evaluation.default_policy')
        );
        $this->assertSame(70.0, $criterion['score']);
        $this->assertSame('ready', $criterion['readiness_status']);
        $this->assertSame('talqin', $criterion['inputs']['requirements'][0]['quran_mode']);
        $this->assertCount(4, $criterion['inputs']['requirements'][0]['attendance_days']);
        $this->assertSame([10, 11], $criterion['inputs']['requirements'][0]['memorized_page_numbers']);
        $this->assertDatabaseCount('quran_period_assessments', 0);
    }

    public function test_reading_score_is_loaded_from_operational_reading_improvement_record(): void
    {
        $context = $this->context();
        $record = ReadingImprovement::create([
            'student' => $context['student']->id,
            'course' => $context['course']->id,
            'type' => 'significant_improvement',
            'description' => 'سجل تشغيلي سابق للتقييم النهائي.',
        ]);

        $criterion = app(ReadingCalculator::class)->calculate(
            $context['candidate']->fresh(['cycle', 'enrollments']),
            config('evaluation.default_policy')
        );

        $this->assertSame(25, $criterion['score']);
        $this->assertSame('ready', $criterion['readiness_status']);
        $this->assertSame($record->id, $criterion['inputs']['source_record_id']);
        $this->assertSame('reading_improvements', $criterion['rule_trace']['source']);
    }

    public function test_theoretical_exam_score_is_loaded_from_exams_and_subject_maximum(): void
    {
        $context = $this->context();
        $subject = Subject::create([
            'name' => 'فقه',
            'description' => 'اختبار تلقائي المصدر',
            'min_marks' => 0,
            'max_marks' => 80,
            'course_id' => $context['course']->id,
        ]);
        $exam = Exam::create([
            'student' => $context['student']->id,
            'subject' => $subject->id,
            'course' => $context['course']->id,
            'mark' => 60,
        ]);

        $criterion = app(TheoreticalExamCalculator::class)->calculate(
            $context['candidate']->fresh(['cycle', 'enrollments']),
            config('evaluation.default_policy')
        );

        $this->assertSame(75.0, $criterion['score']);
        $this->assertSame('ready', $criterion['readiness_status']);
        $this->assertSame($exam->id, $criterion['inputs']['subjects'][0]['source_record_id']);
        $this->assertSame('exams', $criterion['rule_trace']['source']);
    }

    public function test_exam_correction_after_the_data_cutoff_stays_in_the_calculation(): void
    {
        $context = $this->context();
        $subject = Subject::create([
            'name' => 'تجويد',
            'description' => 'اختبار نافذة المصدر',
            'min_marks' => 0,
            'max_marks' => 100,
            'course_id' => $context['course']->id,
        ]);
        $exam = Exam::create([
            'student' => $context['student']->id,
            'subject' => $subject->id,
            'course' => $context['course']->id,
            'mark' => 40,
        ]);

        // الدورة أُغلقت، ثم صحّح الموظف العلامة بعد الإغلاق. النافذة على updated_at
        // كانت تدفع السجل خارج المدى فتختفي العلامة ويسقط المعيار إلى صفر.
        $cutoff = $context['candidate']->cycle->end_date->copy()->endOfDay();
        $context['candidate']->cycle->update(['data_cutoff_at' => $cutoff]);
        $exam->forceFill([
            'mark' => 90,
            'updated_at' => $cutoff->copy()->addWeek(),
        ])->save();

        $criterion = app(TheoreticalExamCalculator::class)->calculate(
            $context['candidate']->fresh(['cycle', 'enrollments']),
            config('evaluation.default_policy')
        );

        $this->assertSame('ready', $criterion['readiness_status']);
        $this->assertSame(90.0, $criterion['inputs']['subjects'][0]['score']);
        $this->assertSame($exam->id, $criterion['inputs']['subjects'][0]['source_record_id']);
    }

    public function test_administration_score_is_loaded_from_warning_deductions(): void
    {
        $context = $this->context();
        $warning = Warning::create([
            'student' => $context['student']->id,
            'warner' => $context['teacher']->id,
            'title' => 'مخالفة سلوكية',
            'description' => 'سجل إنذار تشغيلي.',
            'deduction_points' => 4,
        ]);

        $criterion = app(AdministrationEvaluationCalculator::class)->calculate(
            $context['candidate']->fresh(['cycle', 'enrollments']),
            config('evaluation.default_policy')
        );

        $this->assertSame(46.0, $criterion['score']);
        $this->assertSame($warning->id, $criterion['inputs']['source_warnings'][0]['id']);
        // مصدر وحيد بعد حذف جدول ملاحظات السلوك: الإنذارات.
        $this->assertSame('warnings', $criterion['rule_trace']['source']);
        $this->assertArrayNotHasKey('legacy_observation_deductions', $criterion['rule_trace']);
    }

    public function test_late_entry_stays_in_the_cycle_when_it_carries_the_event_date(): void
    {
        $context = $this->context();
        $subject = Subject::create([
            'name' => 'تجويد',
            'description' => 'اختبار الرصد المتأخر',
            'min_marks' => 0,
            'max_marks' => 100,
            'course_id' => $context['course']->id,
        ]);

        // الرصد المتأخر هو الحالة الطبيعية: الاختبار يجري داخل الدورة وتُدخل
        // علامته بعد إغلاقها. حين كانت النافذة على لحظة الكتابة كانت العلامة
        // والحسم يسقطان من الحساب بلا أي أثر ظاهر للموظف.
        $writtenAfterTheCycle = $context['candidate']->cycle->end_date->copy()->addWeek();
        // الساعة تتقدّم إلى ما بعد إغلاق الدورة، فالحدّ الأعلى للنافذة يصبح
        // نهاية الدورة لا اللحظة الراهنة.
        Date::setTestNow($writtenAfterTheCycle);

        $exam = Exam::create([
            'student' => $context['student']->id,
            'subject' => $subject->id,
            'course' => $context['course']->id,
            'mark' => 80,
        ]);
        $exam->forceFill([
            'occurred_at' => $context['candidate']->cycle->end_date->copy()->subDay(),
            'created_at' => $writtenAfterTheCycle,
        ])->save();

        $warning = Warning::create([
            'student' => $context['student']->id,
            'warner' => $context['teacher']->id,
            'title' => 'مخالفة داخل الدورة رُصدت بعدها',
            'deduction_points' => 4,
        ]);
        $warning->forceFill([
            'occurred_at' => $context['candidate']->cycle->end_date->copy()->subDay(),
            'created_at' => $writtenAfterTheCycle,
        ])->save();

        $candidate = $context['candidate']->fresh(['cycle', 'enrollments']);
        $policy = config('evaluation.default_policy');

        $exams = app(TheoreticalExamCalculator::class)->calculate($candidate, $policy);
        $this->assertSame(80.0, $exams['score']);
        $this->assertSame('ready', $exams['readiness_status']);

        $administration = app(AdministrationEvaluationCalculator::class)->calculate($candidate, $policy);
        $this->assertSame(46.0, $administration['score']);
    }

    public function test_records_outside_the_window_are_named_instead_of_vanishing(): void
    {
        $context = $this->context();
        $subject = Subject::create([
            'name' => 'تجويد',
            'description' => 'اختبار التشخيص',
            'min_marks' => 0,
            'max_marks' => 100,
            'course_id' => $context['course']->id,
        ]);

        // بتاريخ حدث بعد إغلاق الدورة: السجل خارج النافذة فعلاً،
        // والمطلوب أن يُسمّى لا أن تتحول النتيجة إلى صفر صامت.
        $outside = $context['candidate']->cycle->end_date->copy()->addWeek();
        Date::setTestNow($outside);
        Exam::create([
            'student' => $context['student']->id,
            'subject' => $subject->id,
            'course' => $context['course']->id,
            'mark' => 80,
        ])->forceFill(['occurred_at' => $outside, 'created_at' => $outside])->save();

        Warning::create([
            'student' => $context['student']->id,
            'warner' => $context['teacher']->id,
            'title' => 'إنذار خارج النافذة',
            'deduction_points' => 4,
        ])->forceFill(['occurred_at' => $outside, 'created_at' => $outside])->save();

        $candidate = $context['candidate']->fresh(['cycle', 'enrollments']);
        $policy = config('evaluation.default_policy');

        $exams = app(TheoreticalExamCalculator::class)->calculate($candidate, $policy);
        $this->assertSame(0.0, $exams['score']);
        $this->assertSame(1, $exams['inputs']['excluded_out_of_window_count']);
        $this->assertTrue(
            collect($exams['warnings'])->contains(fn ($warning) => str_contains($warning, 'خارج نافذة الدورة')),
            'العلامة الساقطة يجب أن تُسمّى في تحذيرات المعيار لا أن تختفي.'
        );

        $administration = app(AdministrationEvaluationCalculator::class)->calculate($candidate, $policy);
        $this->assertSame(50.0, $administration['score']);
        $this->assertSame(1, $administration['inputs']['excluded_out_of_window_count']);
        $this->assertTrue(
            collect($administration['warnings'])->contains(fn ($warning) => str_contains($warning, 'خارج نافذة الدورة')),
            'درجة إدارة كاملة مع وجود إنذار خارج النافذة يجب أن تُعلَّل.'
        );
    }

    public function test_sabr_bonus_is_loaded_directly_and_awarded_once_per_student_part(): void
    {
        $context = $this->context();
        Sabr::create([
            'student' => $context['student']->id,
            'giver' => $context['teacher']->id,
            'course' => $context['course']->id,
            'type' => 'داخلي',
            'date' => '2025-12-31',
            'parts' => [1],
        ]);
        Sabr::create([
            'student' => $context['student']->id,
            'giver' => $context['teacher']->id,
            'course' => $context['course']->id,
            'type' => 'داخلي',
            'date' => '2026-01-05',
            'parts' => [1, 2],
        ]);
        Sabr::create([
            'student' => $context['student']->id,
            'giver' => $context['teacher']->id,
            'course' => $context['course']->id,
            'type' => 'أوقاف',
            'date' => '2026-01-06',
            'parts' => [3],
        ]);

        $criterion = app(SabrBonusCalculator::class)->calculate(
            $context['candidate']->fresh(['cycle', 'enrollments']),
            config('evaluation.default_policy')
        );

        $this->assertSame(65.0, $criterion['score']);
        $this->assertSame([2, 3], collect($criterion['inputs']['achievements'])->pluck('part_number')->all());
        $this->assertSame('sabrs', $criterion['rule_trace']['source']);
        $this->assertTrue($criterion['rule_trace']['once_per_student_part']);
    }

    public function test_teacher_score_is_out_of_50_and_averaged_across_periods(): void
    {
        $context = $this->context();
        foreach ([
            [$context['periods'][0], 20, 20, 10],
            [$context['periods'][1], 10, 15, 5],
        ] as [$period, $behavior, $participation, $opinion]) {
            TeacherPeriodEvaluation::create([
                'evaluation_candidate_id' => $context['candidate']->id,
                'evaluation_period_id' => $period->id,
                'circle_id' => $context['circle']->id,
                'evaluator_id' => $context['teacher']->id,
                'behavior_score' => $behavior,
                'participation_score' => $participation,
                'teacher_opinion_score' => $opinion,
                'total_score' => $behavior + $participation + $opinion,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
        }

        $criterion = app(TeacherEvaluationCalculator::class)->calculate(
            $context['candidate']->fresh(['cycle.periods', 'enrollments']),
            config('evaluation.default_policy')
        );

        $this->assertSame(40.0, $criterion['score']);
        $this->assertSame(50, $criterion['maximum_score']);
        $this->assertSame('ready', $criterion['readiness_status']);
    }

    public function test_excellence_requires_hard_gates_and_allows_only_one_soft_failure(): void
    {
        $criteria = $this->excellenceCriteria();
        $evaluator = app(ExcellenceEvaluator::class);

        $oneSoftFailure = $evaluator->evaluate($criteria, config('evaluation.default_policy'));
        $this->assertTrue($oneSoftFailure['is_excellent']);
        $this->assertSame(1, $oneSoftFailure['failed_soft_requirements_count']);

        $criteria[2]['inputs']['type'] = 'decline';
        $twoSoftFailures = $evaluator->evaluate($criteria, config('evaluation.default_policy'));
        $this->assertFalse($twoSoftFailures['is_excellent']);
        $this->assertSame(2, $twoSoftFailures['failed_soft_requirements_count']);

        $criteria = $this->excellenceCriteria();
        $criteria[0]['inputs']['attendance_percentage'] = 59.99;
        $hardFailure = $evaluator->evaluate($criteria, config('evaluation.default_policy'));
        $this->assertFalse($hardFailure['is_excellent']);
        $this->assertFalse($hardFailure['hard_requirements_passed']);
    }

    public function test_mobile_teacher_lists_and_reviews_only_assigned_candidates(): void
    {
        $context = $this->context();
        $teacherRole = Role::create([
            'name' => 'mobile-evaluation-teacher',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Teacher->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);
        $teacherRole->givePermissionTo([
            'إدخال تقييم المدرس',
            'إدخال تقييم القرآن',
        ]);
        $context['teacher']->syncRoles([$teacherRole]);
        $token = $context['teacher']->createToken(
            'mobile-evaluation-test',
            [config('auth_tokens.mobile.abilities.access')],
            now()->addHour()
        );
        $this->withToken($token->plainTextToken);

        $this->getJson('/api/mobile/teacher/evaluation-candidates')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $context['candidate']->id)
            ->assertJsonPath(
                'data.0.enrollments.0.teacher_id',
                $context['teacher']->id
            );
        $this->getJson(
            "/api/mobile/teacher/evaluation-candidates/{$context['candidate']->id}/review"
        )->assertOk();

        $otherTeacher = $this->createUser(
            'other-mobile-evaluation-teacher@example.com',
            '0991000099'
        );
        $otherTeacher->syncRoles([$teacherRole]);
        $otherToken = $otherTeacher->createToken(
            'other-mobile-evaluation-test',
            [config('auth_tokens.mobile.abilities.access')],
            now()->addHour()
        );
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken->plainTextToken);

        $this->getJson('/api/mobile/teacher/evaluation-candidates')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson(
            "/api/mobile/teacher/evaluation-candidates/{$context['candidate']->id}/review"
        )->assertForbidden();
    }

    public function test_student_can_only_read_their_own_published_final_result(): void
    {
        $context = $this->context();
        $other = $this->createStudent($context['mosque'], 'other-final-result');
        $studentRole = Role::create([
            'name' => 'final-result-student',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Student->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);
        $studentRole->givePermissionTo([
            config('roles.student_capabilities.final_results'),
            config('roles.student_capabilities.certificates'),
        ]);
        $context['student']->syncRoles([$studentRole]);
        $other->syncRoles([$studentRole]);

        $ownResult = $this->createPublishedResult($context['cycle'], $context['candidate'], $context['supervisor']);
        $otherCandidate = EvaluationCandidate::create([
            'evaluation_cycle_id' => $context['cycle']->id,
            'student_id' => $other->id,
            'mosque_id' => $context['mosque']->id,
            'status' => 'active',
        ]);
        $otherResult = $this->createPublishedResult($context['cycle'], $otherCandidate, $context['supervisor'], 2);
        $token = $context['student']->createToken(
            'final-result-test',
            [config('auth_tokens.mobile.abilities.access')],
            now()->addHour()
        );
        $this->withToken($token->plainTextToken);

        $this->getJson('/api/mobile/student/me/final-results')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownResult->id);
        $this->getJson("/api/mobile/student/me/final-results/{$ownResult->id}")->assertOk();
        $this->getJson("/api/mobile/student/me/final-results/{$otherResult->id}")->assertNotFound();

        $ownResult->update(['status' => 'approved', 'published_at' => null]);
        $this->getJson("/api/mobile/student/me/final-results/{$ownResult->id}")->assertNotFound();
    }

    public function test_official_run_persists_a_complete_versioned_result_snapshot(): void
    {
        $context = $this->context();
        $session = CourseDate::create([
            'course_id' => $context['course']->id,
            'session_date' => '2026-01-02',
            'status' => 'held',
            'counts_for_attendance' => true,
        ]);
        StudentCourseAbsence::create([
            'student' => $context['student']->id,
            'course' => $context['course']->id,
            'course_date_id' => $session->id,
            'circle_id' => $context['circle']->id,
            'type' => 'present',
            'source' => 'camera',
            'external_reference' => 'official-run-attendance',
            'captured_at' => now(),
            'date' => $session->session_date,
        ]);

        ReadingImprovement::create([
            'student' => $context['student']->id,
            'course' => $context['course']->id,
            'type' => 'significant_improvement',
            'baseline_score' => 4,
            'final_score' => 8,
            'baseline_level' => 'level_1',
            'final_level' => 'level_1',
            'difference' => 4,
            'promotion_recommended' => false,
            'status' => 'submitted',
        ]);
        $inputs = app(EvaluationInputService::class);
        foreach ($context['periods'] as $period) {
            $inputs->saveTeacher($context['candidate'], [
                'evaluation_period_id' => $period->id,
                'circle_id' => $context['circle']->id,
                'behavior_score' => 20,
                'participation_score' => 20,
                'teacher_opinion_score' => 10,
                'status' => 'submitted',
            ], $context['teacher']);
        }

        $context['cycle']->update(['status' => 'ready']);
        $run = app(EvaluationRunService::class)->run(
            $context['cycle']->fresh(),
            $context['supervisor'],
            false
        );
        $result = $run->results->sole();

        $this->assertFalse($run->is_preview);
        $this->assertSame('completed', $run->status);
        $this->assertSame(255.0, (float) $result->base_score);
        $this->assertSame(255.0, (float) $result->base_maximum);
        $this->assertSame(255.0, (float) $result->final_score);
        $this->assertSame(100.0, (float) $result->final_percentage);
        $this->assertTrue($result->is_excellent);
        $this->assertSame(1, $result->rank);
        $this->assertCount(7, $result->criteria);
        $this->assertSame('calculated', $context['cycle']->fresh()->status);
        $this->assertNotNull($context['cycle']->fresh()->data_cutoff_at);
        $this->assertSame($run->id, $context['cycle']->fresh()->latestFinalRun->id);
        $this->assertSame(
            config('evaluation.default_policy.schema_version'),
            $run->policy_snapshot['schema_version']
        );
    }

    public function test_recognition_keeps_all_honors_but_assigns_only_one_material_gift(): void
    {
        $context = $this->context();
        $result = $this->createPublishedResult(
            $context['cycle'],
            $context['candidate'],
            $context['supervisor']
        );
        $result->criteria()
            ->where('criterion_key', 'attendance')
            ->update(['inputs' => ['attendance_percentage' => 100]]);
        EvaluationCriterionResult::create([
            'evaluation_result_id' => $result->id,
            'criterion_key' => 'sabr_bonus',
            'criterion_name' => 'نقاط اختبار السبر الناجح',
            'is_applicable' => true,
            'score' => 25,
            'maximum_score' => 0,
            'inputs' => ['achievements' => [['part_number' => 1]]],
            'rule_trace' => ['once_per_student_part' => true],
            'readiness_status' => 'ready',
        ]);

        $batch = app(RecognitionService::class)->generate(
            $result->run->fresh(),
            $context['supervisor']
        );
        $awards = $batch->awards->where('evaluation_result_id', $result->id);

        $this->assertCount(3, $awards);
        $this->assertSame(1, $awards->where('receives_material_gift', true)->count());
        $this->assertEqualsCanonicalizing(
            ['top_student', 'perfect_attendance', 'sabr_success'],
            $awards->pluck('award_type')->all()
        );
    }

    private function context(): array
    {
        Date::setTestNow('2026-01-10 12:00:00');
        $supervisor = $this->createUser('evaluation-supervisor@example.com', '0991000001');
        $teacher = $this->createUser('evaluation-teacher@example.com', '0991000002');
        $mosque = Mosque::create(['name' => 'مسجد التقييم', 'mosque_code' => 'EVAL01']);
        $project = Project::create([
            'name' => 'مشروع التقييم',
            'description' => 'اختبار قواعد التقييم النهائي.',
            'audience' => 'الطلاب',
            'supervisor' => $supervisor->id,
            'is_active' => true,
        ]);
        $course = Course::create([
            'name' => 'مقرر التقييم',
            'description' => 'مقرر',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'supervisor_id' => $supervisor->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_active' => true,
        ]);
        $circle = Circle::create([
            'name' => 'حلقة التقييم',
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'quran_mode' => 'none',
        ]);
        $student = $this->createStudent($mosque, 'final-evaluation-owner');
        $policy = EvaluationPolicy::create([
            'name' => 'سياسة الاختبار',
            'version' => 1,
            'status' => 'approved',
            'configuration' => config('evaluation.default_policy'),
            'approved_by' => $supervisor->id,
            'approved_at' => now(),
        ]);
        $cycle = EvaluationCycle::create([
            'project_id' => $project->id,
            'policy_id' => $policy->id,
            'name' => 'شتاء 2026',
            'season' => 'winter',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'data_collection',
            'top_students_count' => 15,
            'created_by' => $supervisor->id,
        ]);
        $cycle->courses()->attach($course->id);
        $periods = collect([
            ['الفترة الأولى', 1, '2026-01-01', '2026-01-15'],
            ['الفترة الثانية', 2, '2026-01-16', '2026-01-31'],
        ])->map(fn ($data) => EvaluationPeriod::create([
            'evaluation_cycle_id' => $cycle->id,
            'name' => $data[0],
            'sequence' => $data[1],
            'start_date' => $data[2],
            'end_date' => $data[3],
            'status' => 'data_collection',
        ]))->values();
        $candidate = EvaluationCandidate::create([
            'evaluation_cycle_id' => $cycle->id,
            'student_id' => $student->id,
            'mosque_id' => $mosque->id,
            'academic_class_snapshot' => $student->academic_class,
            'reading_level_snapshot' => $student->reading_level,
            'status' => 'active',
        ]);
        EvaluationCandidateEnrollment::create([
            'evaluation_candidate_id' => $candidate->id,
            'course_id' => $course->id,
            'circle_id' => $circle->id,
            'teacher_id' => $teacher->id,
            'course_name_snapshot' => $course->name,
            'circle_name_snapshot' => $circle->name,
            'quran_mode_snapshot' => 'none',
            'teacher_name_snapshot' => trim($teacher->first_name.' '.$teacher->last_name),
        ]);

        return compact(
            'supervisor',
            'teacher',
            'mosque',
            'project',
            'course',
            'circle',
            'student',
            'policy',
            'cycle',
            'periods',
            'candidate'
        );
    }

    private function createUser(string $email, string $phone): User
    {
        return User::create([
            'first_name' => 'مستخدم',
            'last_name' => 'اختبار',
            'email' => $email,
            'phone' => $phone,
            'birth_date' => '1990-01-01',
            'password' => 'password123',
        ]);
    }

    private function createStudent(Mosque $mosque, string $username): Student
    {
        return Student::create([
            'mosque_id' => $mosque->id,
            'first_name' => 'طالب',
            'last_name' => $username,
            'username' => $username,
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_2',
            'father_name' => 'ولي الأمر',
            'parent_social_state' => 'married',
            'father_phone' => '098'.str_pad((string) Student::count(), 7, '0', STR_PAD_LEFT),
            'password' => 'student123',
        ]);
    }

    private function excellenceCriteria(): array
    {
        return [
            [
                'key' => 'attendance',
                'is_applicable' => true,
                'score' => 90,
                'inputs' => ['attendance_percentage' => 90],
            ],
            [
                'key' => 'theoretical_exams',
                'is_applicable' => true,
                'score' => 70,
                'inputs' => [],
            ],
            [
                'key' => 'reading',
                'is_applicable' => true,
                'score' => -5,
                'inputs' => ['type' => 'no_improvement'],
            ],
            [
                'key' => 'quran',
                'is_applicable' => true,
                'score' => 0,
                'inputs' => ['pages_completed' => 4, 'target_pages' => 10],
            ],
            [
                'key' => 'teacher_evaluation',
                'is_applicable' => true,
                'score' => 30,
                'inputs' => [],
            ],
            [
                'key' => 'administration_evaluation',
                'is_applicable' => true,
                'score' => 45,
                'inputs' => [],
            ],
        ];
    }

    public function test_student_must_reconfirm_identity_before_downloading_the_certificate(): void
    {
        Storage::fake('local');
        $context = $this->context();
        $studentRole = Role::create([
            'name' => 'certificate-download-student',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Student->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);
        $studentRole->givePermissionTo([
            config('roles.student_capabilities.final_results'),
            config('roles.student_capabilities.certificates'),
        ]);
        $context['student']->syncRoles([$studentRole]);
        $context['student']->forceFill(['selfnumber' => 'EVAL01-000123'])->save();

        $result = $this->createPublishedResult(
            $context['cycle'],
            $context['candidate'],
            $context['supervisor']
        );
        $certificate = $this->createIssuedCertificate($result, $context['supervisor']);

        $token = $context['student']->createToken(
            'certificate-download-test',
            [config('auth_tokens.mobile.abilities.access')],
            now()->addHour()
        );
        $this->withToken($token->plainTextToken);
        $endpoint = "/api/mobile/student/me/certificates/{$certificate->id}/download";

        // رمز الجلسة وحده لا يسلّم الشهادة: بلا حقول التأكيد الطلب غير صالح.
        $this->postJson($endpoint)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier', 'password']);

        // كلمة مرور خاطئة، ومعرّف طالب آخر، كلاهما يُرفض.
        $this->postJson($endpoint, [
            'identifier' => 'final-evaluation-owner',
            'password' => 'wrong-password',
        ])->assertForbidden();

        $this->postJson($endpoint, [
            'identifier' => 'someone-else',
            'password' => 'student123',
        ])->assertForbidden();

        // اسم المستخدم، ثم الرقم الذاتي الحالي بفروق الحالة والمسافات الجانبية.
        foreach (['final-evaluation-owner', '  eval01-000123  '] as $identifier) {
            $response = $this->post($endpoint, [
                'identifier' => $identifier,
                'password' => 'student123',
            ]);

            $response->assertOk();
            $this->assertSame(
                'application/pdf',
                $response->headers->get('content-type')
            );
        }

        // خمس محاولات في الدقيقة سقف مقصود: الواجهة تفحص كلمة مرور فهي سطح
        // تخمين. الطلب السادس يُردّ ولو كان تأكيده صحيحاً.
        $this->postJson($endpoint, [
            'identifier' => 'final-evaluation-owner',
            'password' => 'student123',
        ])->assertStatus(429);

        // لا يوجد مسار GET موازٍ يتخطى التأكيد.
        $this->get("/api/mobile/student/me/certificates/{$certificate->id}")
            ->assertNotFound();
    }

    public function test_confirmed_student_cannot_download_another_students_certificate(): void
    {
        Storage::fake('local');
        $context = $this->context();
        $studentRole = Role::create([
            'name' => 'certificate-isolation-student',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Student->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);
        $studentRole->givePermissionTo([
            config('roles.student_capabilities.certificates'),
        ]);
        $other = $this->createStudent($context['mosque'], 'certificate-other-owner');
        $context['student']->syncRoles([$studentRole]);
        $other->syncRoles([$studentRole]);

        $otherCandidate = EvaluationCandidate::create([
            'evaluation_cycle_id' => $context['cycle']->id,
            'student_id' => $other->id,
            'mosque_id' => $context['mosque']->id,
            'status' => 'active',
        ]);
        $otherResult = $this->createPublishedResult(
            $context['cycle'],
            $otherCandidate,
            $context['supervisor']
        );
        $otherCertificate = $this->createIssuedCertificate(
            $otherResult,
            $context['supervisor']
        );

        $token = $context['student']->createToken(
            'certificate-isolation-test',
            [config('auth_tokens.mobile.abilities.access')],
            now()->addHour()
        );
        $this->withToken($token->plainTextToken);

        // تأكيد هوية صحيح تماماً، لكن الشهادة تخص طالباً آخر.
        $this->postJson(
            "/api/mobile/student/me/certificates/{$otherCertificate->id}/download",
            [
                'identifier' => 'final-evaluation-owner',
                'password' => 'student123',
            ]
        )->assertNotFound();
    }

    private function createIssuedCertificate(
        EvaluationResult $result,
        User $actor
    ): Certificate {
        $serial = 'QIMS-TEST-'.$result->id;
        $path = 'certificates/final-results/'.$serial.'.pdf';
        // ملف حقيقي على قرص مزيّف: التحميل يتحقق من بصمة sha256 قبل الإرسال.
        $bytes = "%PDF-1.4\n% certificate test fixture\n";
        Storage::disk('local')->put($path, $bytes);

        return Certificate::create([
            'evaluation_result_id' => $result->id,
            'certificate_type' => 'final_result',
            'serial_number' => $serial,
            'verification_token_hash' => hash('sha256', 'token-'.$result->id),
            'file_disk' => 'local',
            'file_path' => $path,
            'file_sha256' => hash('sha256', $bytes),
            'data_snapshot' => ['result' => ['final_score' => '425.00']],
            'status' => 'issued',
            'version' => 1,
            'issued_by' => $actor->id,
            'issued_at' => now(),
        ]);
    }

    private function createPublishedResult(
        EvaluationCycle $cycle,
        EvaluationCandidate $candidate,
        User $actor,
        int $sequence = 1
    ): EvaluationResult {
        $run = EvaluationRun::create([
            'evaluation_cycle_id' => $cycle->id,
            'policy_id' => $cycle->policy_id,
            'sequence' => $sequence,
            'status' => 'completed',
            'is_preview' => false,
            'policy_snapshot' => config('evaluation.default_policy'),
            'readiness_snapshot' => ['is_ready' => true],
            'initiated_by' => $actor->id,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        $result = EvaluationResult::create([
            'evaluation_run_id' => $run->id,
            'evaluation_candidate_id' => $candidate->id,
            'base_score' => 400,
            'base_maximum' => 455,
            'bonus_score' => 25,
            'final_score' => 425,
            'final_percentage' => 93.41,
            'is_excellent' => true,
            'excellence_checks' => ['is_excellent' => true],
            'rank' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
        EvaluationCriterionResult::create([
            'evaluation_result_id' => $result->id,
            'criterion_key' => 'attendance',
            'criterion_name' => 'الحضور',
            'is_applicable' => true,
            'score' => 120,
            'maximum_score' => 130,
            'inputs' => ['attendance_percentage' => 97.5],
            'rule_trace' => [],
            'readiness_status' => 'ready',
        ]);

        return $result;
    }
}
