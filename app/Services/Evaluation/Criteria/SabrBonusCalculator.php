<?php

namespace App\Services\Evaluation\Criteria;

use App\Models\EvaluationCandidate;
use App\Models\Sabr;

class SabrBonusCalculator
{
    public function calculate(EvaluationCandidate $candidate, array $policy): array
    {
        $candidate->loadMissing(['cycle', 'enrollments']);
        $courseIds = $candidate->enrollments
            ->pluck('course_id')
            ->map(fn ($courseId) => (int) $courseId)
            ->unique();
        $seenParts = [];
        $achievements = collect();

        Sabr::query()
            ->where('student', $candidate->student_id)
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->each(function (Sabr $sabr) use (
                $candidate,
                $courseIds,
                $policy,
                &$seenParts,
                $achievements
            ): void {
                $belongsToCycle = $courseIds->contains((int) $sabr->course)
                    && $sabr->date->betweenIncluded(
                        $candidate->cycle->start_date,
                        $candidate->cycle->end_date
                    );
                $sourceType = $sabr->type === 'أوقاف' ? 'awqaf' : 'internal';

                foreach (collect($sabr->parts)->map(fn ($part) => (int) $part)->unique() as $part) {
                    if ($part < 1 || $part > 30 || isset($seenParts[$part])) {
                        continue;
                    }

                    $seenParts[$part] = true;
                    if (! $belongsToCycle) {
                        continue;
                    }

                    $achievements->push([
                        'source_record_id' => $sabr->id,
                        'part_number' => $part,
                        'source_type' => $sourceType,
                        'bonus_points' => (float) $policy['sabr_bonus'][$sourceType],
                        'first_success_at' => $sabr->date->startOfDay()->toIso8601String(),
                    ]);
                }
            });

        $score = (float) $achievements->sum('bonus_points');

        return [
            'key' => 'sabr_bonus',
            'name' => 'نقاط اختبار السبر الناجح',
            'is_applicable' => $achievements->isNotEmpty(),
            'score' => round($score, 2),
            'maximum_score' => 0,
            'inputs' => [
                'achievements' => $achievements->values()->all(),
            ],
            'rule_trace' => [
                'source' => 'sabrs',
                'internal_points' => $policy['sabr_bonus']['internal'],
                'awqaf_points' => $policy['sabr_bonus']['awqaf'],
                'once_per_student_part' => true,
                'every_recorded_part_assessment_is_successful' => true,
            ],
            'readiness_status' => 'ready',
            'warnings' => [],
        ];
    }
}
