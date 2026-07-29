<?php

namespace App\Services\Evaluation\Criteria;

use App\Models\AdministrationBehaviorObservation;
use App\Models\EvaluationCandidate;
use App\Services\Evaluation\EvaluationSourceService;

class AdministrationEvaluationCalculator
{
    public function __construct(private readonly EvaluationSourceService $sources) {}

    public function calculate(EvaluationCandidate $candidate, array $policy): array
    {
        $settings = $policy['administration_evaluation'];
        $warnings = $this->sources->warnings($candidate);
        $observations = AdministrationBehaviorObservation::query()
            ->where('evaluation_candidate_id', $candidate->id)
            ->where('status', 'approved')
            ->whereNull('reversed_at')
            ->orderBy('occurred_at')
            ->get();

        $warningDeductions = (float) $warnings->sum('deduction_points');
        $legacyObservationDeductions = (float) $observations->sum('deduction_points');
        $deductions = $warningDeductions + $legacyObservationDeductions;
        $score = max(0, $settings['maximum_score'] - $deductions);

        return [
            'key' => 'administration_evaluation',
            'name' => 'تقييم الإدارة',
            'is_applicable' => true,
            'score' => round($score, 2),
            'maximum_score' => $settings['maximum_score'],
            'inputs' => [
                'initial_score' => $settings['maximum_score'],
                'total_deductions' => round($deductions, 2),
                'warning_count' => $warnings->count(),
                'observation_count' => $observations->count(),
                'source_warnings' => $warnings->map(fn ($warning) => [
                    'id' => $warning->id,
                    'title' => $warning->title,
                    'description' => $warning->description,
                    'deduction_points' => (float) $warning->deduction_points,
                    'occurred_at' => $warning->created_at?->toIso8601String(),
                ])->values()->all(),
                'observations' => $observations->map(fn ($observation) => [
                    'id' => $observation->id,
                    'description' => $observation->description,
                    'deduction_points' => (float) $observation->deduction_points,
                    'occurred_at' => $observation->occurred_at?->toIso8601String(),
                ])->values()->all(),
            ],
            'rule_trace' => [
                'deduction_range' => [
                    $settings['minimum_deduction'],
                    $settings['maximum_deduction'],
                ],
                'primary_source' => 'warnings',
                'warning_deductions' => round($warningDeductions, 2),
                'legacy_observation_deductions' => round($legacyObservationDeductions, 2),
                'only_approved_non_reversed' => true,
            ],
            'readiness_status' => 'ready',
            'warnings' => [],
        ];
    }
}
