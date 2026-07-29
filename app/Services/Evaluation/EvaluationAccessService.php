<?php

namespace App\Services\Evaluation;

use App\Models\EvaluationCandidate;
use App\Models\EvaluationCycle;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EvaluationAccessService
{
    public function scopeVisibleCycles(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->hasFullFieldOperationsAccess()) {
            return $query;
        }

        return $query->where(function ($scope) use ($user) {
            $scope->whereHas('project', fn ($project) => $project->where('supervisor', $user->id))
                ->orWhereHas('courses', fn ($course) => $course->where('supervisor_id', $user->id))
                ->orWhereHas(
                    'candidates.enrollments',
                    fn ($enrollment) => $enrollment->where('teacher_id', $user->id)
                );
        });
    }

    public function canConfigureProject(User $user, Project $project): bool
    {
        return $user->isSuperAdmin()
            || $user->hasFullFieldOperationsAccess()
            || $project->supervisor === $user->id;
    }

    public function canViewCycle(User $user, EvaluationCycle $cycle): bool
    {
        if (
            $user->isSuperAdmin()
            || $user->hasFullFieldOperationsAccess()
            || $cycle->project()->where('supervisor', $user->id)->exists()
        ) {
            return true;
        }

        return $cycle->courses()->where('supervisor_id', $user->id)->exists()
            || $cycle->candidates()
                ->whereHas('enrollments', fn ($query) => $query->where('teacher_id', $user->id))
                ->exists();
    }

    public function canManageCycle(User $user, EvaluationCycle $cycle): bool
    {
        return $user->isSuperAdmin()
            || $user->hasFullFieldOperationsAccess()
            || $cycle->project()->where('supervisor', $user->id)->exists()
            || $cycle->courses()->where('supervisor_id', $user->id)->exists();
    }

    public function canApproveCycle(User $user, EvaluationCycle $cycle): bool
    {
        return $user->isSuperAdmin()
            || $user->hasFullFieldOperationsAccess()
            || $cycle->project()->where('supervisor', $user->id)->exists();
    }

    public function canEvaluateCandidate(
        User $user,
        EvaluationCandidate $candidate,
        ?int $circleId = null
    ): bool {
        if ($user->hasFullFieldOperationsAccess()) {
            return true;
        }

        if ($this->canManageCycle($user, $candidate->cycle)) {
            return true;
        }

        return $candidate->enrollments()
            ->where('teacher_id', $user->id)
            ->when($circleId, fn ($query) => $query->where('circle_id', $circleId))
            ->exists();
    }
}
