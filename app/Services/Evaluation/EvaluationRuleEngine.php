<?php

namespace App\Services\Evaluation;

use Illuminate\Support\Arr;

class EvaluationRuleEngine
{
    public function apply(array $criterion, ?array $ruleSet): array
    {
        if (! $ruleSet || ! ($ruleSet['enabled'] ?? true) || ! ($criterion['is_applicable'] ?? false)) {
            return $criterion;
        }

        $context = [
            'inputs' => $criterion['inputs'] ?? [],
            'original_score' => (float) ($criterion['score'] ?? 0),
            'original_maximum_score' => (float) ($criterion['maximum_score'] ?? 0),
        ];
        $matchedRule = null;

        foreach ($ruleSet['rules'] ?? [] as $rule) {
            if ($this->matches($rule, $context)) {
                $matchedRule = $rule;
                break;
            }
        }

        $outcome = $matchedRule['score'] ?? $ruleSet['default_score'] ?? ['type' => 'original'];
        $score = $this->score($outcome, $context);
        if (! ($ruleSet['is_bonus'] ?? false)) {
            $score = min((float) ($ruleSet['maximum_score'] ?? $criterion['maximum_score']), $score);
        }

        $criterion['score'] = round($score, 2);
        if (! ($ruleSet['is_bonus'] ?? false)) {
            $criterion['maximum_score'] = (float) ($ruleSet['maximum_score'] ?? $criterion['maximum_score']);
        }
        $criterion['rule_trace']['dynamic_rule'] = [
            'schema_version' => 1,
            'matched_rule_id' => $matchedRule['id'] ?? null,
            'matched_rule_label' => $matchedRule['label'] ?? 'النتيجة الافتراضية',
            'conditions' => $matchedRule['conditions'] ?? [],
            'score_definition' => $outcome,
            'original_score' => $context['original_score'],
            'calculated_score' => $criterion['score'],
        ];

        return $criterion;
    }

    private function matches(array $rule, array $context): bool
    {
        $conditions = $rule['conditions'] ?? [];
        if ($conditions === []) {
            return false;
        }

        $results = collect($conditions)->map(function (array $condition) use ($context) {
            $left = Arr::get($context, $condition['field']);
            $right = ($condition['compare_with'] ?? 'value') === 'field'
                ? Arr::get($context, $condition['compare_field'])
                : ($condition['value'] ?? null);

            return $this->compare($left, $right, $condition['operator'] ?? 'equals');
        });

        return ($rule['match'] ?? 'all') === 'any'
            ? $results->contains(true)
            : $results->every(fn ($result) => $result);
    }

    private function compare(mixed $left, mixed $right, string $operator): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            $left = (float) $left;
            $right = (float) $right;
        }

        return match ($operator) {
            'equals' => $left === $right,
            'not_equals' => $left !== $right,
            'greater_than' => $left > $right,
            'greater_than_or_equal' => $left >= $right,
            'less_than' => $left < $right,
            'less_than_or_equal' => $left <= $right,
            default => false,
        };
    }

    private function score(array $outcome, array $context): float
    {
        if (($outcome['type'] ?? null) === 'original') {
            return $context['original_score'];
        }

        if (($outcome['type'] ?? null) === 'fixed') {
            return (float) ($outcome['value'] ?? 0);
        }

        $score = (float) ($outcome['constant'] ?? 0);
        foreach ($outcome['terms'] ?? [] as $term) {
            $score += ((float) Arr::get($context, $term['field'], 0))
                * ((float) ($term['coefficient'] ?? 0));
        }

        if (($outcome['minimum'] ?? null) !== null) {
            $score = max((float) $outcome['minimum'], $score);
        }
        if (($outcome['maximum'] ?? null) !== null) {
            $score = min((float) $outcome['maximum'], $score);
        }

        return $score;
    }
}
