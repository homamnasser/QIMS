<?php

namespace App\Services\Evaluation;

use App\Models\EvaluationPolicy;
use App\Models\User;
use Illuminate\Support\Collection;
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
        return $this->store("قواعد {$cycleName} — مشروع {$projectId}", $ruleConfiguration, 'approved', $actor);
    }

    /**
     * قالب قواعد قابل لإعادة الاستخدام: سياسة لا ترتبط بدورة، تميزها الحالة
     * `template` فلا يلتقطها احتساب ولا تنشأ مع دورة. الحفظ باسم موجود ينتج
     * نسخة أحدث منه بدل الاصطدام بقيد التفرد.
     */
    public function saveTemplate(string $name, array $ruleConfiguration, User $actor): EvaluationPolicy
    {
        return $this->store($name, $ruleConfiguration, 'template', $actor);
    }

    /**
     * أحدث نسخة من كل قالب فقط: القالب يعرف باسمه، والنسخ الأقدم تاريخ لا خيار.
     *
     * ponytail: التجميع في الذاكرة يكفي لعدد القوالب المنسقة؛ إن كبر العدد فاستبدله
     * باستعلام فرعي مرتبط يختار أكبر نسخة لكل اسم.
     *
     * @return Collection<int, EvaluationPolicy>
     */
    public function templates()
    {
        return EvaluationPolicy::query()
            ->where('status', 'template')
            ->orderByDesc('version')
            ->get(['id', 'name', 'version', 'updated_at'])
            ->groupBy('name')
            ->map->first()
            ->sortByDesc('updated_at')
            ->values();
    }

    private function store(string $name, array $ruleConfiguration, string $status, User $actor): EvaluationPolicy
    {
        $rules = $this->ruleDefinitions->normalize($ruleConfiguration);

        return DB::transaction(function () use ($rules, $name, $status, $actor) {
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
                'status' => $status,
                'configuration' => $configuration,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
        });
    }
}
