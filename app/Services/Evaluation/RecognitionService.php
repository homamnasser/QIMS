<?php

namespace App\Services\Evaluation;

use App\Models\EvaluationRun;
use App\Models\RecognitionAward;
use App\Models\RecognitionBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecognitionService
{
    public function generate(EvaluationRun $run, User $actor): RecognitionBatch
    {
        $run->loadMissing(['cycle.periods', 'results.criteria']);
        $policy = $run->policy_snapshot;
        $topCount = $run->cycle->top_students_count
            ?: $policy['recognition']['top_students_count'];
        $requiredWinterPeriod = (int) $policy['recognition']['after_period_sequence'];

        if ($run->cycle->season === $policy['recognition']['season']
            && (int) $run->cycle->periods->max('sequence') < $requiredWinterPeriod) {
            throw ValidationException::withMessages([
                'recognition' => [
                    "لا ينشأ تكريم الشتاء قبل اكتمال الفترة رقم {$requiredWinterPeriod}.",
                ],
            ]);
        }

        return DB::transaction(function () use ($run, $actor, $policy, $topCount) {
            $batch = RecognitionBatch::updateOrCreate(
                [
                    'evaluation_cycle_id' => $run->evaluation_cycle_id,
                    'evaluation_run_id' => $run->id,
                ],
                [
                    'name' => 'تكريم '.$run->cycle->name,
                    'status' => 'draft',
                    'created_by' => $actor->id,
                ]
            );

            $batch->awards()->delete();
            $topResultIds = [];

            foreach ($run->results->where('is_excellent', true)->whereNotNull('rank') as $result) {
                if ($result->rank > $topCount) {
                    continue;
                }

                $topResultIds[] = $result->id;
                RecognitionAward::create([
                    'recognition_batch_id' => $batch->id,
                    'evaluation_result_id' => $result->id,
                    'award_type' => 'top_student',
                    'reward_tier' => $this->tierForRank($result->rank),
                    'receives_material_gift' => true,
                    'metadata' => ['rank' => $result->rank],
                ]);
            }

            foreach ($run->results as $result) {
                $criteria = $result->criteria->keyBy('criterion_key');
                $attendance = $criteria->get('attendance');
                $sabr = $criteria->get('sabr_bonus');
                $alreadyTop = in_array($result->id, $topResultIds, true);
                $materialGiftAssigned = $alreadyTop;

                if (($attendance?->inputs['attendance_percentage'] ?? 0)
                    >= $policy['recognition']['perfect_attendance_percentage']) {
                    $receivesGift = ! $policy['recognition']['avoid_duplicate_material_gifts']
                        || ! $materialGiftAssigned;
                    RecognitionAward::create([
                        'recognition_batch_id' => $batch->id,
                        'evaluation_result_id' => $result->id,
                        'award_type' => 'perfect_attendance',
                        'reward_tier' => 'symbolic',
                        'receives_material_gift' => $receivesGift,
                        'suppression_reason' => $receivesGift
                            ? null
                            : ($alreadyTop
                                ? 'already_receives_top_student_gift'
                                : 'already_receives_recognition_gift'),
                        'metadata' => [
                            'attendance_percentage' => $attendance->inputs['attendance_percentage'],
                        ],
                    ]);
                    $materialGiftAssigned = $materialGiftAssigned || $receivesGift;
                }

                if ((float) ($sabr?->score ?? 0) > 0) {
                    $receivesGift = ! $policy['recognition']['avoid_duplicate_material_gifts']
                        || ! $materialGiftAssigned;
                    RecognitionAward::create([
                        'recognition_batch_id' => $batch->id,
                        'evaluation_result_id' => $result->id,
                        'award_type' => 'sabr_success',
                        'reward_tier' => 'symbolic',
                        'receives_material_gift' => $receivesGift,
                        'suppression_reason' => $receivesGift
                            ? null
                            : ($alreadyTop
                                ? 'already_receives_top_student_gift'
                                : 'already_receives_recognition_gift'),
                        'metadata' => ['bonus_score' => (float) $sabr->score],
                    ]);
                }
            }

            return $batch->load('awards');
        });
    }

    private function tierForRank(int $rank): string
    {
        return match ($rank) {
            1 => 'first',
            2 => 'second',
            3 => 'third',
            default => 'top_group',
        };
    }
}
