<?php

namespace App\Services\Evaluation;

use App\Models\CourseDate;
use App\Models\EvaluationCandidate;
use App\Models\EvaluationCycle;
use App\Models\EvaluationPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EvaluationSessionService
{
    public function attendanceSessions(EvaluationCandidate $candidate): Collection
    {
        $candidate->loadMissing(['cycle.periods', 'enrollments']);

        $query = CourseDate::query()
            ->whereIn('course_id', $candidate->enrollments->pluck('course_id')->unique())
            ->where('counts_for_attendance', true);

        return $this->withinPeriods($query, $candidate->cycle->periods)
            ->orderBy('session_date')
            ->get();
    }

    public function cycleCourseDates(EvaluationCycle $cycle, int $courseId): Collection
    {
        $cycle->loadMissing('periods');

        return $this->withinPeriods(
            CourseDate::query()
                ->where('course_id', $courseId)
                ->where('counts_for_attendance', true),
            $cycle->periods
        )->orderBy('session_date')->get();
    }

    public function periodCourseDates(
        int $courseId,
        EvaluationPeriod $period
    ): Collection {
        return CourseDate::query()
            ->where('course_id', $courseId)
            ->whereBetween('session_date', [
                $period->start_date->toDateString(),
                $period->end_date->toDateString(),
            ])
            ->where('counts_for_attendance', true)
            ->orderBy('session_date')
            ->get();
    }

    private function withinPeriods(Builder $query, Collection $periods): Builder
    {
        if ($periods->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scope) use ($periods): void {
            foreach ($periods as $period) {
                $scope->orWhereBetween('session_date', [
                    $period->start_date->toDateString(),
                    $period->end_date->toDateString(),
                ]);
            }
        });
    }
}
