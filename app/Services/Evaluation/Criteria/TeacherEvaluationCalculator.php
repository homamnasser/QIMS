<?php

namespace App\Services\Evaluation\Criteria;

use App\Models\EvaluationCandidate;
use App\Models\TeacherPeriodEvaluation;

class TeacherEvaluationCalculator
{
    public function calculate(EvaluationCandidate $candidate, array $policy): array
    {
        $settings = $policy['teacher_evaluation'];
        $candidate->loadMissing(['cycle.periods', 'enrollments']);

        $requiredKeys = [];
        foreach ($candidate->cycle->periods as $period) {
            foreach ($candidate->enrollments->unique('circle_id') as $enrollment) {
                $requiredKeys[] = $period->id.'|'.$enrollment->circle_id;
            }
        }

        $evaluations = TeacherPeriodEvaluation::query()
            ->where('evaluation_candidate_id', $candidate->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->get();
        $actualKeys = $evaluations
            ->map(fn ($evaluation) => $evaluation->evaluation_period_id.'|'.$evaluation->circle_id)
            ->all();
        $missingKeys = array_values(array_diff($requiredKeys, $actualKeys));

        $score = $evaluations->isNotEmpty()
            ? (float) $evaluations->avg('total_score')
            : 0;

        $ready = $requiredKeys !== [] && $missingKeys === [];

        return [
            'key' => 'teacher_evaluation',
            'name' => 'تقييم المدرس في نهاية كل فترة',
            'is_applicable' => true,
            'score' => round(min($settings['maximum_score'], max(0, $score)), 2),
            'maximum_score' => $settings['maximum_score'],
            'inputs' => [
                'evaluations' => $evaluations->map(fn ($evaluation) => [
                    'period_id' => $evaluation->evaluation_period_id,
                    'circle_id' => $evaluation->circle_id,
                    'behavior_score' => (float) $evaluation->behavior_score,
                    'participation_score' => (float) $evaluation->participation_score,
                    'teacher_opinion_score' => (float) $evaluation->teacher_opinion_score,
                    'total_score' => (float) $evaluation->total_score,
                ])->values()->all(),
                'average_total_score' => round($score, 4),
                'required_count' => count($requiredKeys),
                'completed_count' => $evaluations->count(),
                'missing_keys' => $missingKeys,
            ],
            'rule_trace' => [
                'behavior_maximum' => $settings['behavior_maximum'],
                'participation_homework_maximum' => $settings['participation_homework_maximum'],
                'teacher_opinion_maximum' => $settings['teacher_opinion_maximum'],
                'aggregation' => $settings['aggregation'],
            ],
            'readiness_status' => $ready ? 'ready' : 'missing',
            'warnings' => $ready ? [] : ['تقييم المدرس غير مكتمل لكل الفترات والحلقات المطلوبة.'],
        ];
    }
}
