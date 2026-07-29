<?php

namespace App\Services\Evaluation;

use App\Models\EvaluationCandidate;
use App\Models\EvaluationCycle;
use App\Models\Sabr;
use App\Models\SabrPartAchievement;
use Illuminate\Support\Facades\DB;

class SabrAchievementService
{
    public function reconcile(EvaluationCycle $cycle, array $policy): int
    {
        $cycle->loadMissing('candidates.enrollments');
        $changes = 0;

        DB::transaction(function () use ($cycle, $policy, &$changes) {
            foreach ($cycle->candidates->where('status', 'active') as $candidate) {
                $changes += $this->reconcileCandidate($candidate, $policy);
            }
        });

        return $changes;
    }

    private function reconcileCandidate(EvaluationCandidate $candidate, array $policy): int
    {
        $courseIds = $candidate->enrollments->pluck('course_id')->unique();
        $sabrs = Sabr::query()
            ->where('student', $candidate->student_id)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
        $changes = 0;

        foreach ($sabrs as $sabr) {
            $sourceType = $sabr->type === 'أوقاف' ? 'awqaf' : 'internal';
            $bonus = $policy['sabr_bonus'][$sourceType];
            $belongsToCycle = $courseIds->contains($sabr->course)
                && $sabr->date->betweenIncluded(
                    $candidate->cycle->start_date,
                    $candidate->cycle->end_date
                );

            foreach (collect($sabr->parts)->map(fn ($part) => (int) $part)->unique() as $part) {
                if ($part < 1 || $part > 30) {
                    continue;
                }

                $achievement = SabrPartAchievement::query()
                    ->where('student_id', $candidate->student_id)
                    ->where('part_number', $part)
                    ->lockForUpdate()
                    ->first();

                if (! $achievement) {
                    SabrPartAchievement::create([
                        'student_id' => $candidate->student_id,
                        'sabr_id' => $sabr->id,
                        'evaluation_candidate_id' => $belongsToCycle ? $candidate->id : null,
                        'part_number' => $part,
                        'source_type' => $sourceType,
                        'bonus_points' => $bonus,
                        'first_success_at' => $sabr->date->startOfDay(),
                        'evidence_reference' => 'sabr:'.$sabr->id,
                        'verified_by' => $sabr->giver,
                    ]);
                    $changes++;

                    continue;
                }

                if ($achievement->sabr_id === $sabr->id
                    && $achievement->evaluation_candidate_id === null
                    && $belongsToCycle) {
                    $achievement->update(['evaluation_candidate_id' => $candidate->id]);
                    $changes++;
                }
            }
        }

        return $changes;
    }
}
