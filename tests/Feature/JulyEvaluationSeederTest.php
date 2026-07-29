<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseDate;
use App\Models\EvaluationCycle;
use App\Models\Exam;
use App\Models\Project;
use App\Models\QuranPeriodAssessment;
use App\Models\ReadingImprovement;
use App\Models\SabrPartAchievement;
use App\Models\Student;
use App\Models\StudentCourseAbsence;
use App\Models\TeacherPeriodEvaluation;
use App\Models\User;
use App\Services\Evaluation\EvaluationCalculator;
use App\Services\Evaluation\EvaluationCandidateSyncService;
use App\Services\Evaluation\EvaluationCycleService;
use App\Services\Evaluation\EvaluationPolicyService;
use App\Services\Evaluation\EvaluationRunService;
use Database\Seeders\JulyEvaluationCriteriaSeeder;
use Database\Seeders\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JulyEvaluationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_complete_ready_july_evaluation_cycle(): void
    {
        $this->seed(TestDataSeeder::class);

        $cycle = EvaluationCycle::query()
            ->where('name', 'التقييم التجريبي الكامل - تموز 2026')
            ->with(['periods', 'courses', 'candidates', 'runs.results.criteria'])
            ->firstOrFail();

        $this->assertSame('data_collection', $cycle->status);
        $this->assertCount(2, $cycle->periods);
        $this->assertCount(2, $cycle->courses);
        $this->assertCount(4, $cycle->candidates);
        $this->assertSame('2026-07-01', $cycle->start_date->toDateString());
        $this->assertSame('2026-07-28', $cycle->end_date->toDateString());

        $this->assertDatabaseCount('teacher_period_evaluations', 8);
        $this->assertDatabaseCount('quran_period_assessments', 8);
        $this->assertDatabaseMissing('quran_period_assessments', [
            'below_minimum' => true,
        ]);
        $this->assertDatabaseCount('administration_behavior_observations', 4);
        $this->assertDatabaseCount('evaluation_runs', 1);
        $this->assertDatabaseCount('evaluation_results', 4);
        $this->assertDatabaseCount('evaluation_criterion_results', 28);
        $this->assertSame(
            5,
            SabrPartAchievement::query()
                ->whereIn('evaluation_candidate_id', $cycle->candidates->pluck('id'))
                ->count()
        );
        $this->assertSame(
            8,
            Exam::query()
                ->whereIn('student', $cycle->candidates->pluck('student_id'))
                ->whereBetween('updated_at', [
                    '2026-07-01 00:00:00',
                    '2026-07-28 23:59:59',
                ])
                ->count()
        );

        $this->assertSame(
            16,
            CourseDate::query()
                ->whereIn('course_id', $cycle->courses->pluck('id'))
                ->whereBetween('session_date', ['2026-07-01', '2026-07-28'])
                ->count()
        );
        $this->assertSame(
            32,
            StudentCourseAbsence::query()
                ->where('source', 'july_evaluation_seed')
                ->count()
        );
        $this->assertEqualsCanonicalizing(
            ['present', 'full', 'first_period', 'second_period'],
            StudentCourseAbsence::query()
                ->where('source', 'july_evaluation_seed')
                ->distinct()
                ->pluck('type')
                ->all()
        );
        $this->assertSame(
            2,
            StudentCourseAbsence::query()
                ->where('source', 'july_evaluation_seed')
                ->where('is_excused', true)
                ->count()
        );
        $this->assertEqualsCanonicalizing(
            [
                'significant_improvement',
                'slight_improvement',
                'no_improvement',
                'decline',
            ],
            ReadingImprovement::query()
                ->whereNull('evaluation_candidate_id')
                ->whereNull('evaluation_period_id')
                ->pluck('type')
                ->all()
        );

        $readiness = app(EvaluationRunService::class)->readiness($cycle);
        $this->assertTrue($readiness['is_ready']);
        $this->assertSame(4, $readiness['ready_candidate_count']);
        $policy = app(EvaluationPolicyService::class)
            ->configuration($cycle->policy);
        foreach ($cycle->candidates as $candidate) {
            $criteria = collect(
                app(EvaluationCalculator::class)
                    ->calculate($candidate, $policy)['criteria']
            )->keyBy('key');

            foreach (['reading', 'quran', 'teacher_evaluation'] as $key) {
                $this->assertSame(
                    'ready',
                    $criteria->get($key)['readiness_status'],
                    "{$candidate->student_id}: {$key}"
                );
            }
            $this->assertGreaterThan(0, $criteria->get('quran')['score']);
        }
        $this->assertTrue($cycle->runs->first()->is_preview);
        $this->assertEqualsCanonicalizing(
            [
                'attendance',
                'reading',
                'quran',
                'theoretical_exams',
                'teacher_evaluation',
                'administration_evaluation',
                'sabr_bonus',
            ],
            $cycle->runs->first()->results->first()->criteria
                ->pluck('criterion_key')
                ->all()
        );
        $this->assertTrue(
            $cycle->runs->first()->results->every(
                fn ($result) => $result->criteria->count() === 7
            )
        );
    }

    public function test_criteria_seeder_completes_all_students_in_another_july_cycle_idempotently(): void
    {
        $this->seed(TestDataSeeder::class);

        $actor = User::query()
            ->where('email', 'superadmin@gmail.com')
            ->firstOrFail();
        $project = Project::query()
            ->where('name', 'مشروع إتقان القراءة')
            ->firstOrFail();
        $courseIds = Course::query()
            ->where('project_id', $project->id)
            ->pluck('id')
            ->all();
        $cycle = app(EvaluationCycleService::class)->create([
            'project_id' => $project->id,
            'name' => 'دورة تموز الإضافية',
            'season' => 'summer',
            'top_students_count' => 4,
            'course_ids' => $courseIds,
            'periods' => [
                [
                    'name' => 'الفترة الأولى',
                    'sequence' => 1,
                    'start_date' => '2026-07-01',
                    'end_date' => '2026-07-15',
                ],
                [
                    'name' => 'الفترة الثانية',
                    'sequence' => 2,
                    'start_date' => '2026-07-16',
                    'end_date' => '2026-07-28',
                ],
            ],
        ], $actor);
        app(EvaluationCandidateSyncService::class)->sync($cycle);

        $draftCandidate = $cycle->candidates()
            ->with('enrollments')
            ->whereHas('student', fn ($query) => $query
                ->where('username', 'kenan-alhouri'))
            ->firstOrFail();
        $draftEnrollment = $draftCandidate->enrollments->first();
        $draftPeriod = $cycle->periods()->orderBy('sequence')->firstOrFail();
        TeacherPeriodEvaluation::create([
            'evaluation_candidate_id' => $draftCandidate->id,
            'evaluation_period_id' => $draftPeriod->id,
            'circle_id' => $draftEnrollment->circle_id,
            'evaluator_id' => $actor->id,
            'behavior_score' => 1,
            'participation_score' => 1,
            'teacher_opinion_score' => 1,
            'total_score' => 3,
            'evidence' => [],
            'comments' => 'مسودة يجب أن يستكملها Seeder تموز.',
            'status' => 'draft',
        ]);

        $this->seed(JulyEvaluationCriteriaSeeder::class);
        $readingCount = ReadingImprovement::query()
            ->whereNull('evaluation_candidate_id')
            ->whereNull('evaluation_period_id')
            ->count();
        $teacherCount = TeacherPeriodEvaluation::query()->count();
        $candidateIds = $cycle->candidates()->pluck('id');
        $quranCount = QuranPeriodAssessment::query()
            ->whereIn('evaluation_candidate_id', $candidateIds)
            ->count();

        $this->seed(JulyEvaluationCriteriaSeeder::class);

        $this->assertSame(
            $readingCount,
            ReadingImprovement::query()
                ->whereNull('evaluation_candidate_id')
                ->whereNull('evaluation_period_id')
                ->count()
        );
        $this->assertSame(
            $teacherCount,
            TeacherPeriodEvaluation::query()->count()
        );
        $this->assertSame(
            $quranCount,
            QuranPeriodAssessment::query()
                ->whereIn('evaluation_candidate_id', $candidateIds)
                ->count()
        );
        $this->assertSame(8, $quranCount);
        $this->assertDatabaseHas('teacher_period_evaluations', [
            'id' => TeacherPeriodEvaluation::query()
                ->where('evaluation_candidate_id', $draftCandidate->id)
                ->where('evaluation_period_id', $draftPeriod->id)
                ->where('circle_id', $draftEnrollment->circle_id)
                ->value('id'),
            'status' => 'submitted',
            'total_score' => 44,
        ]);
        $this->assertDatabaseMissing('quran_period_assessments', [
            'evaluation_candidate_id' => $candidateIds->first(),
            'below_minimum' => true,
        ]);

        $student = Student::query()
            ->where('username', 'hammam-nasser')
            ->firstOrFail();
        $candidate = $cycle->candidates()
            ->with(['cycle.periods', 'cycle.policy', 'enrollments', 'student'])
            ->where('student_id', $student->id)
            ->firstOrFail();
        $policy = app(EvaluationPolicyService::class)
            ->configuration($candidate->cycle->policy);
        $criteria = collect(
            app(EvaluationCalculator::class)->calculate($candidate, $policy)['criteria']
        )->keyBy('key');

        $this->assertSame('ready', $criteria->get('reading')['readiness_status']);
        $this->assertSame(25, $criteria->get('reading')['score']);
        $this->assertSame(
            'ready',
            $criteria->get('teacher_evaluation')['readiness_status']
        );
        $this->assertSame(50.0, $criteria->get('teacher_evaluation')['score']);
        $this->assertSame(
            2,
            TeacherPeriodEvaluation::query()
                ->where('evaluation_candidate_id', $candidate->id)
                ->count()
        );

        $readiness = app(EvaluationRunService::class)->readiness($cycle);
        $this->assertTrue($readiness['is_ready']);
        $this->assertSame(4, $readiness['ready_candidate_count']);
        $this->assertTrue(
            collect($readiness['candidates'])->every('is_ready')
        );
        foreach ($cycle->candidates()->with([
            'cycle.periods',
            'cycle.policy',
            'enrollments',
            'student',
        ])->get() as $readyCandidate) {
            $candidateCriteria = collect(
                app(EvaluationCalculator::class)
                    ->calculate($readyCandidate, $policy)['criteria']
            )->keyBy('key');

            $this->assertSame(
                'ready',
                $candidateCriteria->get('quran')['readiness_status']
            );
            $this->assertGreaterThan(
                0,
                $candidateCriteria->get('quran')['score']
            );
        }
    }
}
