<?php

namespace App\Services\Evaluation;

use App\Models\EvaluationCriterionResult;
use App\Models\EvaluationCycle;
use App\Models\EvaluationResult;
use App\Models\EvaluationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluationRunService
{
    public function __construct(
        private readonly EvaluationCalculator $calculator,
        private readonly EvaluationPolicyService $policies,
        private readonly SabrAchievementService $sabrAchievements,
        private readonly EvaluationAuditService $audit,
    ) {}

    public function readiness(EvaluationCycle $cycle): array
    {
        $cycle->loadMissing(['policy', 'periods', 'candidates.enrollments', 'candidates.student']);
        $policy = $this->policies->configuration($cycle->policy);

        $candidates = $cycle->candidates
            ->where('status', 'active')
            ->map(function ($candidate) use ($policy) {
                $calculation = $this->calculator->calculate($candidate, $policy);

                return [
                    'candidate_id' => $candidate->id,
                    'student_id' => $candidate->student_id,
                    'student_name' => trim($candidate->student->first_name.' '.$candidate->student->last_name),
                    'is_ready' => $calculation['is_ready'],
                    'criteria' => collect($calculation['criteria'])->map(fn ($criterion) => [
                        'key' => $criterion['key'],
                        'name' => $criterion['name'],
                        'status' => $criterion['readiness_status'],
                        'warnings' => $criterion['warnings'],
                    ])->values()->all(),
                ];
            })->values();

        return [
            'is_ready' => $candidates->isNotEmpty() && $candidates->every('is_ready'),
            'candidate_count' => $candidates->count(),
            'ready_candidate_count' => $candidates->where('is_ready', true)->count(),
            'candidates' => $candidates->all(),
        ];
    }

    public function run(EvaluationCycle $cycle, User $actor, bool $preview = true): EvaluationRun
    {
        return DB::transaction(function () use ($cycle, $actor, $preview) {
            $lockedCycle = EvaluationCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            if (! $preview && $lockedCycle->status !== 'ready') {
                throw ValidationException::withMessages([
                    'cycle' => ['يجب أن تكون الدورة جاهزة قبل الاحتساب النهائي.'],
                ]);
            }

            $dataCutoff = now();
            $lockedCycle->load([
                'policy',
                'periods',
                'candidates.enrollments',
                'candidates.student',
            ]);
            $policy = $this->policies->configuration($lockedCycle->policy);
            $this->sabrAchievements->reconcile($lockedCycle, $policy);
            $lockedCycle->load([
                'policy',
                'periods',
                'candidates.enrollments',
                'candidates.student',
            ]);
            $computations = $lockedCycle->candidates
                ->where('status', 'active')
                ->mapWithKeys(fn ($candidate) => [
                    $candidate->id => $this->calculator->calculate($candidate, $policy),
                ]);
            $readiness = [
                'is_ready' => $computations->isNotEmpty() && $computations->every('is_ready'),
                'candidate_count' => $computations->count(),
                'not_ready_candidate_ids' => $computations
                    ->filter(fn ($calculation) => ! $calculation['is_ready'])
                    ->keys()
                    ->values()
                    ->all(),
            ];

            if (! $preview && ! $readiness['is_ready']) {
                throw ValidationException::withMessages([
                    'readiness' => [
                        'لا يمكن إنشاء نتيجة نهائية قبل اكتمال كل المدخلات. استخدم فحص الجاهزية لمعرفة النواقص.',
                    ],
                ]);
            }

            $sequence = ((int) $lockedCycle->runs()->max('sequence')) + 1;

            $run = EvaluationRun::create([
                'evaluation_cycle_id' => $lockedCycle->id,
                'policy_id' => $lockedCycle->policy_id,
                'sequence' => $sequence,
                'status' => 'running',
                'is_preview' => $preview,
                'policy_snapshot' => $policy,
                'readiness_snapshot' => $readiness,
                'initiated_by' => $actor->id,
                'started_at' => now(),
            ]);

            $rankable = [];
            foreach ($lockedCycle->candidates()->where('status', 'active')->get() as $candidate) {
                $calculation = $computations->get($candidate->id);
                if (! $calculation) {
                    continue;
                }

                $result = EvaluationResult::create([
                    'evaluation_run_id' => $run->id,
                    'evaluation_candidate_id' => $candidate->id,
                    'base_score' => $calculation['base_score'],
                    'base_maximum' => $calculation['base_maximum'],
                    'bonus_score' => $calculation['bonus_score'],
                    'final_score' => $calculation['final_score'],
                    'final_percentage' => $calculation['final_percentage'],
                    'is_excellent' => $calculation['is_excellent'],
                    'excellence_checks' => $calculation['excellence_checks'],
                    'status' => $preview ? 'preview' : 'calculated',
                ]);

                foreach ($calculation['criteria'] as $criterion) {
                    EvaluationCriterionResult::create([
                        'evaluation_result_id' => $result->id,
                        'criterion_key' => $criterion['key'],
                        'criterion_name' => $criterion['name'],
                        'is_applicable' => $criterion['is_applicable'],
                        'score' => $criterion['score'],
                        'maximum_score' => $criterion['maximum_score'],
                        'inputs' => $criterion['inputs'],
                        'rule_trace' => $criterion['rule_trace'],
                        'readiness_status' => $criterion['readiness_status'],
                        'warnings' => $criterion['warnings'],
                    ]);
                }

                $criteria = collect($calculation['criteria'])->keyBy('key');
                $rankable[] = [
                    'result' => $result,
                    'is_excellent' => $calculation['is_excellent'],
                    'final_score' => $calculation['final_score'],
                    'attendance_percentage' => $criteria->get('attendance')['inputs']['attendance_percentage'] ?? 0,
                    'exam_score' => $criteria->get('theoretical_exams')['score'] ?? 0,
                    'student_id' => $candidate->student_id,
                ];
            }

            $this->assignRanks($rankable);

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);

            if (! $preview) {
                $lockedCycle->update([
                    'status' => 'calculated',
                    'data_cutoff_at' => $dataCutoff,
                ]);
            }

            $this->audit->record(
                $preview ? 'evaluation.preview_calculated' : 'evaluation.final_calculated',
                $run,
                $actor,
                null,
                [
                    'sequence' => $sequence,
                    'candidate_count' => count($rankable),
                    'policy_id' => $lockedCycle->policy_id,
                ]
            );

            return $run->load(['results.candidate.student', 'results.criteria']);
        });
    }

    private function assignRanks(array $rankable): void
    {
        usort($rankable, function (array $left, array $right) {
            if ($left['is_excellent'] !== $right['is_excellent']) {
                return $right['is_excellent'] <=> $left['is_excellent'];
            }

            foreach (['final_score', 'attendance_percentage', 'exam_score'] as $key) {
                if ($left[$key] !== $right[$key]) {
                    return $right[$key] <=> $left[$key];
                }
            }

            return $left['student_id'] <=> $right['student_id'];
        });

        $rank = 0;
        foreach ($rankable as $item) {
            if (! $item['is_excellent']) {
                $item['result']->update(['rank' => null]);

                continue;
            }

            $rank++;
            $item['result']->update(['rank' => $rank]);
        }
    }
}
