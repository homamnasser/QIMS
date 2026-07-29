<?php

namespace App\Services\Evaluation\Criteria;

use App\Models\EvaluationCandidate;
use App\Services\Evaluation\EvaluationSourceService;

class ReadingCalculator
{
    public function __construct(private readonly EvaluationSourceService $sources) {}

    public function calculate(EvaluationCandidate $candidate, array $policy): array
    {
        $settings = $policy['reading'];
        $record = $this->sources->readingRecords($candidate)->first();

        if (! $record) {
            return [
                'key' => 'reading',
                'name' => 'التحسن المعتبر في القراءة',
                'is_applicable' => true,
                'score' => 0,
                'maximum_score' => $settings['maximum_score'],
                'inputs' => [],
                'rule_trace' => ['points' => $settings['points']],
                'readiness_status' => 'missing',
                'warnings' => ['لا يوجد سجل تحسن قراءة في جدول reading_improvements ضمن دورة الطالب.'],
            ];
        }

        $points = $settings['points'][$record->type] ?? 0;

        return [
            'key' => 'reading',
            'name' => 'التحسن المعتبر في القراءة',
            'is_applicable' => true,
            'score' => $points,
            'maximum_score' => $settings['maximum_score'],
            'inputs' => [
                'type' => $record->type,
                'baseline_score' => $record->baseline_score,
                'final_score' => $record->final_score,
                'baseline_level' => $record->baseline_level,
                'final_level' => $record->final_level,
                'difference' => $record->difference,
                'promotion_recommended' => $record->promotion_recommended,
                'source_record_id' => $record->id,
            ],
            'rule_trace' => [
                'source' => 'reading_improvements',
                'source_record_id' => $record->id,
                'stored_type' => $record->type,
                'policy_points' => $points,
                'stored_rule_trace' => $record->rule_trace,
            ],
            'readiness_status' => 'ready',
            'warnings' => [],
        ];
    }
}
