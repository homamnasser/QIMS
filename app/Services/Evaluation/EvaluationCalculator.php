<?php

namespace App\Services\Evaluation;

use App\Models\EvaluationCandidate;
use App\Services\Evaluation\Criteria\AdministrationEvaluationCalculator;
use App\Services\Evaluation\Criteria\AttendanceCalculator;
use App\Services\Evaluation\Criteria\QuranCalculator;
use App\Services\Evaluation\Criteria\ReadingCalculator;
use App\Services\Evaluation\Criteria\SabrBonusCalculator;
use App\Services\Evaluation\Criteria\TeacherEvaluationCalculator;
use App\Services\Evaluation\Criteria\TheoreticalExamCalculator;

class EvaluationCalculator
{
    public function __construct(
        private readonly AttendanceCalculator $attendance,
        private readonly ReadingCalculator $reading,
        private readonly QuranCalculator $quran,
        private readonly TheoreticalExamCalculator $exams,
        private readonly TeacherEvaluationCalculator $teacher,
        private readonly AdministrationEvaluationCalculator $administration,
        private readonly SabrBonusCalculator $sabr,
        private readonly ExcellenceEvaluator $excellence,
        private readonly EvaluationRuleEngine $ruleEngine,
    ) {}

    public function calculate(EvaluationCandidate $candidate, array $policy): array
    {
        $candidate->loadMissing(['cycle.periods', 'enrollments', 'student']);

        $criteria = [
            $this->attendance->calculate($candidate, $policy),
            $this->reading->calculate($candidate, $policy),
            $this->quran->calculate($candidate, $policy),
            $this->exams->calculate($candidate, $policy),
            $this->teacher->calculate($candidate, $policy),
            $this->administration->calculate($candidate, $policy),
        ];
        $bonus = $this->sabr->calculate($candidate, $policy);
        $dynamicRules = collect($policy['criteria_rules']['criteria'] ?? []);
        $criteria = collect($criteria)
            ->map(fn (array $criterion) => $this->ruleEngine->apply(
                $criterion,
                $dynamicRules->get($criterion['key'])
            ))
            ->all();
        $bonus = $this->ruleEngine->apply($bonus, $dynamicRules->get($bonus['key']));
        $excellence = $this->excellence->evaluate($criteria, $policy);

        $baseScore = collect($criteria)
            ->filter(fn ($criterion) => $criterion['is_applicable'])
            ->sum('score');
        $baseMaximum = collect($criteria)
            ->filter(fn ($criterion) => $criterion['is_applicable'])
            ->sum('maximum_score');
        $finalScore = $baseScore + $bonus['score'];

        return [
            'criteria' => [...$criteria, $bonus],
            'base_score' => round($baseScore, 2),
            'base_maximum' => round($baseMaximum, 2),
            'bonus_score' => round($bonus['score'], 2),
            'final_score' => round($finalScore, 2),
            'final_percentage' => $baseMaximum > 0
                ? round(($finalScore / $baseMaximum) * 100, 2)
                : 0,
            'is_excellent' => $excellence['is_excellent'],
            'excellence_checks' => $excellence,
            'is_ready' => collect([...$criteria, $bonus])
                ->every(fn ($criterion) => in_array(
                    $criterion['readiness_status'],
                    ['ready', 'not_applicable'],
                    true
                )),
        ];
    }
}
