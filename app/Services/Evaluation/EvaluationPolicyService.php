<?php

namespace App\Services\Evaluation;

use App\Models\EvaluationPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EvaluationPolicyService
{
    public function defaultPolicy(User $actor): EvaluationPolicy
    {
        $name = config('evaluation.default_policy_name');
        $configuration = config('evaluation.default_policy');

        return DB::transaction(function () use ($name, $configuration, $actor) {
            $latest = EvaluationPolicy::query()
                ->where('name', $name)
                ->latest('version')
                ->lockForUpdate()
                ->first();

            if ($latest && $latest->configuration === $configuration && $latest->status === 'approved') {
                return $latest;
            }

            return EvaluationPolicy::create([
                'name' => $name,
                'version' => ($latest?->version ?? 0) + 1,
                'status' => 'approved',
                'configuration' => $configuration,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
        });
    }

    public function configuration(EvaluationPolicy $policy): array
    {
        return array_replace_recursive(config('evaluation.default_policy'), $policy->configuration ?? []);
    }
}
