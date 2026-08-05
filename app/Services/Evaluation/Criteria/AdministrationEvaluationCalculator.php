<?php

namespace App\Services\Evaluation\Criteria;

use App\Models\EvaluationCandidate;
use App\Services\Evaluation\EvaluationSourceService;

class AdministrationEvaluationCalculator
{
    public function __construct(private readonly EvaluationSourceService $sources) {}

    public function calculate(EvaluationCandidate $candidate, array $policy): array
    {
        $settings = $policy['administration_evaluation'];
        $warnings = $this->sources->warnings($candidate);

        // درجة كاملة قد تعني «لا مخالفات» أو «الإنذارات خارج النافذة»؛ الفرق
        // بينهما هو الفرق بين نتيجة صحيحة وحسم ضائع.
        $excluded = $this->sources->excludedWarnings($candidate);
        $criterionWarnings = array_filter([
            $this->sources->excludedWarningMessage($candidate, $excluded, 'إنذاراً'),
        ]);

        $deductions = (float) $warnings->sum('deduction_points');
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
                'excluded_out_of_window_count' => $excluded['count'],
                'excluded_out_of_window_latest' => $excluded['latest'],
                'source_warnings' => $warnings->map(fn ($warning) => [
                    'id' => $warning->id,
                    'title' => $warning->title,
                    'description' => $warning->description,
                    'deduction_points' => (float) $warning->deduction_points,
                    'occurred_at' => ($warning->occurred_at ?? $warning->created_at)?->toIso8601String(),
                ])->values()->all(),
            ],
            'rule_trace' => [
                'deduction_range' => [
                    $settings['minimum_deduction'],
                    $settings['maximum_deduction'],
                ],
                // مصدر وحيد: الإنذارات. جدول ملاحظات السلوك حُذف بعد أن ثبت أنه
                // بلا أي مسار كتابة في التطبيق — كان يملأه بذرة العرض فقط.
                'source' => 'warnings',
            ],
            'readiness_status' => 'ready',
            'warnings' => array_values($criterionWarnings),
        ];
    }
}
