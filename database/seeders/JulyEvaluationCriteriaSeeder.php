<?php

namespace Database\Seeders;

use App\Models\EvaluationCycle;
use App\Models\ReadingImprovement;
use App\Models\TeacherPeriodEvaluation;
use App\Models\User;
use App\Services\Evaluation\Criteria\QuranCalculator;
use App\Services\Evaluation\EvaluationInputService;
use App\Services\Evaluation\EvaluationPolicyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JulyEvaluationCriteriaSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('  ✅ استكمال معايير جميع طلاب دورة 1–28 تموز...');

        $fallbackActor = User::query()
            ->where('email', 'superadmin@gmail.com')
            ->firstOrFail();
        $inputs = app(EvaluationInputService::class);
        $policies = app(EvaluationPolicyService::class);
        $quranCalculator = app(QuranCalculator::class);
        $readingCount = 0;
        $teacherCount = 0;
        $quranCount = 0;

        $cycles = EvaluationCycle::query()
            ->with([
                'policy',
                'periods',
                'candidates' => fn ($query) => $query
                    ->where('status', 'active')
                    ->with(['student', 'enrollments.teacher']),
            ])
            ->whereIn('status', ['draft', 'data_collection'])
            ->whereDate('start_date', '2026-07-01')
            ->whereDate('end_date', '2026-07-28')
            ->get();

        foreach ($cycles as $cycle) {
            foreach ($cycle->candidates as $candidate) {
                $profile = $this->profileFor($candidate->student->username);

                foreach ($candidate->enrollments as $enrollment) {
                    $teacher = $enrollment->teacher ?? $fallbackActor;
                    $readingCount += $this->seedReading(
                        $candidate->student_id,
                        $enrollment->course_id,
                        $teacher->id,
                        $profile['reading']
                    );

                    foreach ($cycle->periods as $period) {
                        $exists = TeacherPeriodEvaluation::query()
                            ->where('evaluation_candidate_id', $candidate->id)
                            ->where('evaluation_period_id', $period->id)
                            ->where('circle_id', $enrollment->circle_id)
                            ->whereIn('status', ['submitted', 'approved'])
                            ->exists();
                        if ($exists) {
                            continue;
                        }

                        [$behavior, $participation, $opinion] =
                            $profile['teacher'][$period->sequence]
                                ?? $profile['teacher']['default'];
                        $inputs->saveTeacher($candidate, [
                            'evaluation_period_id' => $period->id,
                            'circle_id' => $enrollment->circle_id,
                            'behavior_score' => $behavior,
                            'participation_score' => $participation,
                            'teacher_opinion_score' => $opinion,
                            'comments' => 'تقييم مدرّس تجريبي موثق لنهاية فترة تموز.',
                            'status' => 'submitted',
                        ], $teacher);
                        $teacherCount++;
                    }
                }

                $candidate->loadMissing([
                    'cycle.periods',
                    'cycle.policy',
                    'enrollments.teacher',
                ]);
                $quran = $quranCalculator->calculate(
                    $candidate,
                    $policies->configuration($candidate->cycle->policy)
                );
                foreach ($quran['inputs']['requirements'] ?? [] as $requirement) {
                    $enrollment = $candidate->enrollments
                        ->firstWhere('circle_id', $requirement['circle_id']);
                    $teacher = $enrollment?->teacher ?? $fallbackActor;
                    $belowMinimum = (float) $requirement['pages_completed']
                        < (float) $requirement['target_pages'];
                    if (
                        $requirement['manual_review_id']
                        && (bool) $requirement['manual_below_minimum'] === $belowMinimum
                    ) {
                        continue;
                    }

                    $inputs->saveQuran($candidate, [
                        'evaluation_period_id' => $requirement['period_id'],
                        'circle_id' => $requirement['circle_id'],
                        'below_minimum' => $belowMinimum,
                        'notes' => $belowMinimum
                            ? 'الإنجاز المستخرج أقل من هدف الفترة؛ تم اعتماد إشارة دون الحد الأدنى لإكمال المصدر.'
                            : 'تمت مراجعة إنجاز التسميع المستخرج آلياً للفترة.',
                    ], $teacher);
                    $quranCount++;
                }
            }
        }

        $this->command->info("     ├── {$readingCount} سجلات تحسن قراءة جديدة");
        $this->command->info("     ├── {$teacherCount} تقييمات مدرس جديدة");
        $this->command->info("     └── {$quranCount} مراجعات قرآن دورية جديدة");
    }

    private function seedReading(
        int $studentId,
        int $courseId,
        int $teacherId,
        array $profile
    ): int {
        $record = ReadingImprovement::query()
            ->where('student', $studentId)
            ->where('course', $courseId)
            ->whereNull('evaluation_candidate_id')
            ->whereNull('evaluation_period_id')
            ->first();
        $record ??= new ReadingImprovement([
            'student' => $studentId,
            'course' => $courseId,
            'evaluation_candidate_id' => null,
            'evaluation_period_id' => null,
        ]);
        $record->fill([
            'evaluator_id' => $teacherId,
            'type' => $profile['type'],
            'baseline_score' => $profile['baseline_score'],
            'final_score' => $profile['final_score'],
            'baseline_level' => $profile['baseline_level'],
            'final_level' => $profile['final_level'],
            'difference' => round(
                $profile['final_score'] - $profile['baseline_score'],
                2
            ),
            'points' => $profile['points'],
            'promotion_recommended' => $profile['promotion_recommended'],
            'status' => 'approved',
            'rule_trace' => [
                'source' => 'july_evaluation_criteria_seeder',
                'assessment_window' => ['2026-07-01', '2026-07-28'],
                'decision' => $profile['type'],
            ],
            'description' => $profile['description'],
        ]);
        $wasChanged = ! $record->exists || $record->isDirty();
        $record->save();

        DB::table('reading_improvements')->where('id', $record->id)->update([
            'created_at' => '2026-07-27 19:30:00',
            'updated_at' => '2026-07-27 19:30:00',
        ]);

        return $wasChanged ? 1 : 0;
    }

    private function profileFor(string $username): array
    {
        return match ($username) {
            'hammam-nasser' => [
                'reading' => [
                    'type' => 'significant_improvement',
                    'baseline_score' => 5.50,
                    'final_score' => 9.25,
                    'baseline_level' => 'level_2',
                    'final_level' => 'level_3',
                    'points' => 25,
                    'promotion_recommended' => true,
                    'description' => 'تقدم واضح في الطلاقة وضبط مخارج الحروف خلال فترتي تموز.',
                ],
                'teacher' => [
                    1 => [20, 20, 10],
                    2 => [20, 20, 10],
                    'default' => [19, 19, 10],
                ],
            ],
            'kenan-alhouri' => [
                'reading' => [
                    'type' => 'slight_improvement',
                    'baseline_score' => 7.10,
                    'final_score' => 8.30,
                    'baseline_level' => 'level_3',
                    'final_level' => 'level_3',
                    'points' => 10,
                    'promotion_recommended' => false,
                    'description' => 'تحسن بسيط ومستقر في الوقف والابتداء.',
                ],
                'teacher' => [
                    1 => [17, 18, 9],
                    2 => [18, 18, 9],
                    'default' => [17, 18, 9],
                ],
            ],
            'hussein-alhouri' => [
                'reading' => [
                    'type' => 'no_improvement',
                    'baseline_score' => 2.25,
                    'final_score' => 2.25,
                    'baseline_level' => 'level_1',
                    'final_level' => 'level_1',
                    'points' => -5,
                    'promotion_recommended' => false,
                    'description' => 'استقر مستوى القراءة دون تقدم ملموس، مع خطة دعم للشهر التالي.',
                ],
                'teacher' => [
                    1 => [15, 14, 8],
                    2 => [14, 15, 8],
                    'default' => [15, 14, 8],
                ],
            ],
            'abdalrahman-salem' => [
                'reading' => [
                    'type' => 'decline',
                    'baseline_score' => 8.00,
                    'final_score' => 6.50,
                    'baseline_level' => 'level_3',
                    'final_level' => 'level_2',
                    'points' => -15,
                    'promotion_recommended' => false,
                    'description' => 'تراجع مؤقت في الطلاقة يستدعي متابعة فردية وخطة مراجعة مركزة.',
                ],
                'teacher' => [
                    1 => [16, 17, 9],
                    2 => [17, 16, 9],
                    'default' => [16, 17, 9],
                ],
            ],
            default => [
                'reading' => [
                    'type' => 'slight_improvement',
                    'baseline_score' => 5.00,
                    'final_score' => 6.50,
                    'baseline_level' => 'level_2',
                    'final_level' => 'level_2',
                    'points' => 10,
                    'promotion_recommended' => false,
                    'description' => 'تحسن تدريجي في الطلاقة والقراءة خلال شهر تموز.',
                ],
                'teacher' => [
                    1 => [17, 17, 8],
                    2 => [18, 17, 8],
                    'default' => [17, 17, 8],
                ],
            ],
        };
    }
}
