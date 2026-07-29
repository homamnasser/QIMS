<?php

namespace App\Services\Evaluation;

use App\Models\EvaluationPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EvaluationPolicyService
{
    public function __construct(
        private readonly EvaluationRuleDefinitionService $ruleDefinitions,
    ) {}

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

    public function createForCycle(
        array $ruleConfiguration,
        string $cycleName,
        int $projectId,
        User $actor
    ): EvaluationPolicy {
        $rules = $this->ruleDefinitions->normalize($ruleConfiguration);
        $name = "قواعد {$cycleName} — مشروع {$projectId}";

        return DB::transaction(function () use ($rules, $name, $actor) {
            $latestVersion = (int) EvaluationPolicy::query()
                ->where('name', $name)
                ->lockForUpdate()
                ->max('version');
            $configuration = config('evaluation.default_policy');
            $configuration['schema_version'] = max(
                2,
                (int) ($configuration['schema_version'] ?? 1)
            );
            $configuration['criteria_rules'] = $rules;

            return EvaluationPolicy::create([
                'name' => $name,
                'version' => $latestVersion + 1,
                'status' => 'approved',
                'configuration' => $configuration,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
        });
    }
}
