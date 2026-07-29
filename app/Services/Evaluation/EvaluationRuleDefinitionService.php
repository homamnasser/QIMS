<?php

namespace App\Services\Evaluation;

use Illuminate\Validation\ValidationException;

class EvaluationRuleDefinitionService
{
    private const SCHEMA_VERSION = 1;

    private const OPERATORS = [
        'equals',
        'not_equals',
        'greater_than',
        'greater_than_or_equal',
        'less_than',
        'less_than_or_equal',
    ];

    public function template(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'criteria' => [
                [
                    'key' => 'attendance',
                    'name' => 'الحضور',
                    'description' => 'يحوّل نسبة الحضور والغياب والتأخر إلى درجة المعيار.',
                    'enabled' => true,
                    'maximum_score' => 130,
                    'is_bonus' => false,
                    'variables' => [
                        $this->numberVariable('inputs.attendance_percentage', 'نسبة الحضور'),
                        $this->numberVariable('inputs.equivalent_absence', 'الغياب المكافئ'),
                        $this->numberVariable('inputs.total_sessions', 'عدد الجلسات'),
                        $this->numberVariable('inputs.counts.unexcused_absence', 'الغياب غير المبرر'),
                        $this->numberVariable('inputs.counts.excused_absence', 'الغياب المبرر'),
                        $this->numberVariable('inputs.counts.first_period_late', 'تأخر الفترة الأولى'),
                        $this->numberVariable('inputs.counts.second_period_late', 'تأخر الفترة الثانية'),
                    ],
                    'rules' => [
                        $this->fixedRule(
                            'attendance-below-minimum',
                            'دون الحد الأدنى',
                            'inputs.attendance_percentage',
                            'less_than',
                            60,
                            0
                        ),
                        $this->formulaRule(
                            'attendance-normal',
                            'النسبة الاعتيادية',
                            [[
                                'field' => 'inputs.attendance_percentage',
                                'operator' => 'less_than',
                                'compare_with' => 'value',
                                'value' => 90,
                            ]],
                            [
                                ['field' => 'inputs.attendance_percentage', 'coefficient' => 1],
                            ],
                            0,
                            0,
                            130
                        ),
                    ],
                    'default_score' => $this->formulaOutcome(
                        [['field' => 'inputs.attendance_percentage', 'coefficient' => 4]],
                        -270,
                        0,
                        130
                    ),
                ],
                [
                    'key' => 'reading',
                    'name' => 'التحسن المعتبر في القراءة',
                    'description' => 'يربط نوع التحسن المسجل بدرجة موجبة أو سالبة.',
                    'enabled' => true,
                    'maximum_score' => 25,
                    'is_bonus' => false,
                    'variables' => [
                        $this->textVariable('inputs.type', 'نوع التحسن', [
                            ['value' => 'significant_improvement', 'label' => 'تحسن كبير'],
                            ['value' => 'slight_improvement', 'label' => 'تحسن طفيف'],
                            ['value' => 'no_improvement', 'label' => 'دون تحسن'],
                            ['value' => 'decline', 'label' => 'تراجع'],
                        ]),
                        $this->numberVariable('inputs.baseline_score', 'الدرجة الابتدائية'),
                        $this->numberVariable('inputs.final_score', 'الدرجة النهائية'),
                        $this->numberVariable('inputs.difference', 'مقدار الفرق'),
                    ],
                    'rules' => [
                        $this->fixedRule('reading-significant', 'تحسن كبير', 'inputs.type', 'equals', 'significant_improvement', 25),
                        $this->fixedRule('reading-slight', 'تحسن طفيف', 'inputs.type', 'equals', 'slight_improvement', 10),
                        $this->fixedRule('reading-none', 'دون تحسن', 'inputs.type', 'equals', 'no_improvement', -5),
                        $this->fixedRule('reading-decline', 'تراجع', 'inputs.type', 'equals', 'decline', -15),
                    ],
                    'default_score' => $this->fixedOutcome(0),
                ],
                [
                    'key' => 'quran',
                    'name' => 'التسميع والحفظ والمراجعة المستمرة',
                    'description' => 'يقارن الصفحات المنجزة بالهدف ويحسب نقاط الزيادة.',
                    'enabled' => true,
                    'maximum_score' => 100,
                    'is_bonus' => false,
                    'variables' => [
                        $this->numberVariable('inputs.pages_completed', 'الصفحات المنجزة'),
                        $this->numberVariable('inputs.target_pages', 'الصفحات المستهدفة'),
                        $this->numberVariable('inputs.revision_pages', 'صفحات المراجعة'),
                        $this->booleanVariable('inputs.below_minimum', 'مؤشر دون الحد الأدنى'),
                    ],
                    'rules' => [
                        $this->fixedRule('quran-manual-below', 'تأكيد دون الحد الأدنى', 'inputs.below_minimum', 'equals', true, 0),
                        [
                            'id' => 'quran-target-not-reached',
                            'label' => 'لم يبلغ الهدف',
                            'match' => 'all',
                            'conditions' => [[
                                'field' => 'inputs.pages_completed',
                                'operator' => 'less_than',
                                'compare_with' => 'field',
                                'compare_field' => 'inputs.target_pages',
                            ]],
                            'score' => $this->fixedOutcome(0),
                        ],
                    ],
                    'default_score' => $this->formulaOutcome([
                        ['field' => 'inputs.pages_completed', 'coefficient' => 1],
                        ['field' => 'inputs.target_pages', 'coefficient' => -1],
                    ], 70, 0, 100),
                ],
                [
                    'key' => 'theoretical_exams',
                    'name' => 'الامتحانات النظرية',
                    'description' => 'يحوّل متوسط العلامات المعياري إلى درجة الامتحانات.',
                    'enabled' => true,
                    'maximum_score' => 100,
                    'is_bonus' => false,
                    'variables' => [
                        $this->numberVariable('inputs.normalized_percentage', 'متوسط الامتحانات'),
                        $this->numberVariable('inputs.subject_count', 'عدد المواد المحتسبة'),
                    ],
                    'rules' => [],
                    'default_score' => $this->formulaOutcome([
                        ['field' => 'inputs.normalized_percentage', 'coefficient' => 1],
                    ], 0, 0, 100),
                ],
                [
                    'key' => 'teacher_evaluation',
                    'name' => 'تقييم المدرس في نهاية كل فترة',
                    'description' => 'يحسب الدرجة من متوسط تقييمات المدرسين المكتملة.',
                    'enabled' => true,
                    'maximum_score' => 50,
                    'is_bonus' => false,
                    'variables' => [
                        $this->numberVariable('inputs.average_total_score', 'متوسط تقييم المدرس'),
                        $this->numberVariable('inputs.required_count', 'عدد التقييمات المطلوبة'),
                        $this->numberVariable('inputs.completed_count', 'عدد التقييمات المكتملة'),
                    ],
                    'rules' => [],
                    'default_score' => $this->formulaOutcome([
                        ['field' => 'inputs.average_total_score', 'coefficient' => 1],
                    ], 0, 0, 50),
                ],
                [
                    'key' => 'administration_evaluation',
                    'name' => 'تقييم الإدارة',
                    'description' => 'يبدأ من رصيد المعيار ثم يطبق الحسم المسجل.',
                    'enabled' => true,
                    'maximum_score' => 50,
                    'is_bonus' => false,
                    'variables' => [
                        $this->numberVariable('inputs.total_deductions', 'إجمالي الحسم'),
                        $this->numberVariable('inputs.warning_count', 'عدد الإنذارات'),
                        $this->numberVariable('inputs.observation_count', 'عدد الملاحظات'),
                    ],
                    'rules' => [],
                    'default_score' => $this->formulaOutcome([
                        ['field' => 'inputs.total_deductions', 'coefficient' => -1],
                    ], 50, 0, 50),
                ],
                [
                    'key' => 'sabr_bonus',
                    'name' => 'نقاط اختبار السبر الناجح',
                    'description' => 'يجمع نقاط الأجزاء الناجحة بحسب نوع اختبار السبر.',
                    'enabled' => true,
                    'maximum_score' => 0,
                    'is_bonus' => true,
                    'variables' => [
                        $this->numberVariable('inputs.internal_success_count', 'نجاحات السبر الداخلي'),
                        $this->numberVariable('inputs.awqaf_success_count', 'نجاحات سبر الأوقاف'),
                        $this->numberVariable('inputs.achievement_count', 'إجمالي الأجزاء الناجحة'),
                    ],
                    'rules' => [],
                    'default_score' => $this->formulaOutcome([
                        ['field' => 'inputs.internal_success_count', 'coefficient' => 25],
                        ['field' => 'inputs.awqaf_success_count', 'coefficient' => 40],
                    ], 0, 0, null),
                ],
            ],
        ];
    }

    public function normalize(array $configuration): array
    {
        if ((int) ($configuration['schema_version'] ?? 0) !== self::SCHEMA_VERSION) {
            $this->fail('schema_version', 'إصدار بنية القواعد غير مدعوم.');
        }

        if (! is_array($configuration['criteria'] ?? null)) {
            $this->fail('criteria', 'يجب إرسال قواعد معايير التقييم.');
        }

        $templates = collect($this->template()['criteria'])->keyBy('key');
        $provided = collect($configuration['criteria'])
            ->filter(fn ($criterion) => is_array($criterion) && isset($criterion['key']))
            ->keyBy('key');

        if ($provided->count() !== count($configuration['criteria'])) {
            $this->fail('criteria', 'لا يجوز تكرار المعيار أو إرسال معيار بلا مفتاح.');
        }

        $normalized = [];
        foreach ($templates as $key => $template) {
            $criterion = $provided->get($key);
            if (! is_array($criterion)) {
                $this->fail("criteria.{$key}", "قواعد معيار «{$template['name']}» مطلوبة.");
            }

            $allowedVariables = collect($template['variables'])->keyBy('key');
            $maximum = $this->numeric(
                $criterion['maximum_score'] ?? $template['maximum_score'],
                "criteria.{$key}.maximum_score",
                0,
                10000
            );
            $rules = $criterion['rules'] ?? [];
            if (! is_array($rules) || count($rules) > 25) {
                $this->fail("criteria.{$key}.rules", 'يجب ألا يزيد عدد قواعد المعيار على 25 قاعدة.');
            }

            $normalizedRules = [];
            foreach (array_values($rules) as $index => $rule) {
                $normalizedRules[] = $this->normalizeRule(
                    $rule,
                    $allowedVariables,
                    "criteria.{$key}.rules.{$index}",
                    $index
                );
            }

            $normalized[$key] = [
                'key' => $key,
                'name' => $template['name'],
                'description' => $template['description'],
                'enabled' => (bool) ($criterion['enabled'] ?? true),
                'maximum_score' => $maximum,
                'is_bonus' => $template['is_bonus'],
                'rules' => $normalizedRules,
                'default_score' => $this->normalizeOutcome(
                    $criterion['default_score'] ?? null,
                    $allowedVariables,
                    "criteria.{$key}.default_score"
                ),
            ];
        }

        if ($provided->keys()->diff($templates->keys())->isNotEmpty()) {
            $this->fail('criteria', 'توجد معايير غير معروفة ضمن إعدادات القواعد.');
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'criteria' => $normalized,
        ];
    }

    private function normalizeRule(
        mixed $rule,
        $allowedVariables,
        string $path,
        int $index
    ): array {
        if (! is_array($rule)) {
            $this->fail($path, 'صيغة القاعدة غير صالحة.');
        }

        $label = trim((string) ($rule['label'] ?? ''));
        if ($label === '' || mb_strlen($label) > 100) {
            $this->fail("{$path}.label", 'اكتب اسماً واضحاً للقاعدة لا يتجاوز 100 محرف.');
        }

        $match = $rule['match'] ?? 'all';
        if (! in_array($match, ['all', 'any'], true)) {
            $this->fail("{$path}.match", 'نوع مطابقة الشروط غير صالح.');
        }

        $conditions = $rule['conditions'] ?? [];
        if (! is_array($conditions) || $conditions === [] || count($conditions) > 8) {
            $this->fail("{$path}.conditions", 'تحتاج القاعدة إلى شرط واحد على الأقل وبحد أقصى 8 شروط.');
        }

        return [
            'id' => $this->ruleId($rule['id'] ?? null, $index),
            'label' => $label,
            'match' => $match,
            'conditions' => collect(array_values($conditions))
                ->map(fn ($condition, $conditionIndex) => $this->normalizeCondition(
                    $condition,
                    $allowedVariables,
                    "{$path}.conditions.{$conditionIndex}"
                ))
                ->all(),
            'score' => $this->normalizeOutcome(
                $rule['score'] ?? null,
                $allowedVariables,
                "{$path}.score"
            ),
        ];
    }

    private function normalizeCondition(mixed $condition, $allowedVariables, string $path): array
    {
        if (! is_array($condition)) {
            $this->fail($path, 'صيغة الشرط غير صالحة.');
        }

        $field = (string) ($condition['field'] ?? '');
        $variable = $allowedVariables->get($field);
        if (! $variable) {
            $this->fail("{$path}.field", 'مؤشر الشرط غير متاح لهذا المعيار.');
        }

        $operator = (string) ($condition['operator'] ?? '');
        if (! in_array($operator, self::OPERATORS, true)) {
            $this->fail("{$path}.operator", 'معامل المقارنة غير صالح.');
        }

        $compareWith = $condition['compare_with'] ?? 'value';
        if (! in_array($compareWith, ['value', 'field'], true)) {
            $this->fail("{$path}.compare_with", 'مصدر قيمة المقارنة غير صالح.');
        }

        $normalized = [
            'field' => $field,
            'operator' => $operator,
            'compare_with' => $compareWith,
        ];

        if ($compareWith === 'field') {
            $compareField = (string) ($condition['compare_field'] ?? '');
            $compareVariable = $allowedVariables->get($compareField);
            if (! $compareVariable || $compareVariable['type'] !== $variable['type']) {
                $this->fail("{$path}.compare_field", 'يجب اختيار مؤشر آخر من النوع نفسه للمقارنة.');
            }
            $normalized['compare_field'] = $compareField;
        } else {
            $normalized['value'] = $this->normalizeComparisonValue(
                $condition['value'] ?? null,
                $variable,
                "{$path}.value"
            );
        }

        return $normalized;
    }

    private function normalizeOutcome(mixed $outcome, $allowedVariables, string $path): array
    {
        if (! is_array($outcome)) {
            $this->fail($path, 'حدد طريقة احتساب الدرجة.');
        }

        $type = $outcome['type'] ?? null;
        if ($type === 'original') {
            return ['type' => 'original'];
        }

        if ($type === 'fixed') {
            return [
                'type' => 'fixed',
                'value' => $this->numeric($outcome['value'] ?? null, "{$path}.value", -10000, 10000),
            ];
        }

        if ($type !== 'formula') {
            $this->fail("{$path}.type", 'طريقة احتساب الدرجة غير صالحة.');
        }

        $terms = $outcome['terms'] ?? [];
        if (! is_array($terms) || $terms === [] || count($terms) > 8) {
            $this->fail("{$path}.terms", 'تحتاج المعادلة إلى مؤشر واحد على الأقل وبحد أقصى 8 مؤشرات.');
        }

        $normalizedTerms = [];
        foreach (array_values($terms) as $index => $term) {
            if (! is_array($term)) {
                $this->fail("{$path}.terms.{$index}", 'حد المعادلة غير صالح.');
            }
            $field = (string) ($term['field'] ?? '');
            $variable = $allowedVariables->get($field);
            if (! $variable || $variable['type'] !== 'number') {
                $this->fail("{$path}.terms.{$index}.field", 'لا تستخدم المعادلة إلا مؤشرات رقمية.');
            }
            $normalizedTerms[] = [
                'field' => $field,
                'coefficient' => $this->numeric(
                    $term['coefficient'] ?? null,
                    "{$path}.terms.{$index}.coefficient",
                    -10000,
                    10000
                ),
            ];
        }

        $minimum = $this->nullableNumeric($outcome['minimum'] ?? null, "{$path}.minimum", -10000, 10000);
        $maximum = $this->nullableNumeric($outcome['maximum'] ?? null, "{$path}.maximum", -10000, 10000);
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            $this->fail($path, 'الحد الأدنى للمعادلة لا يجوز أن يتجاوز الحد الأعلى.');
        }

        return [
            'type' => 'formula',
            'terms' => $normalizedTerms,
            'constant' => $this->numeric($outcome['constant'] ?? 0, "{$path}.constant", -10000, 10000),
            'minimum' => $minimum,
            'maximum' => $maximum,
        ];
    }

    private function normalizeComparisonValue(mixed $value, array $variable, string $path): mixed
    {
        return match ($variable['type']) {
            'number' => $this->numeric($value, $path, -1000000, 1000000),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                ?? $this->fail($path, 'قيمة المقارنة المنطقية غير صالحة.'),
            default => $this->textValue($value, $variable, $path),
        };
    }

    private function textValue(mixed $value, array $variable, string $path): string
    {
        $value = (string) $value;
        $allowed = collect($variable['options'] ?? [])->pluck('value');
        if ($allowed->isNotEmpty() && ! $allowed->containsStrict($value)) {
            $this->fail($path, 'قيمة المقارنة ليست من الخيارات المتاحة.');
        }
        if (mb_strlen($value) > 255) {
            $this->fail($path, 'قيمة المقارنة النصية طويلة جداً.');
        }

        return $value;
    }

    private function numeric(mixed $value, string $path, float $minimum, float $maximum): float
    {
        if (! is_numeric($value)) {
            $this->fail($path, 'يجب إدخال قيمة رقمية.');
        }
        $number = (float) $value;
        if (! is_finite($number) || $number < $minimum || $number > $maximum) {
            $this->fail($path, "يجب أن تكون القيمة بين {$minimum} و{$maximum}.");
        }

        return $number;
    }

    private function nullableNumeric(
        mixed $value,
        string $path,
        float $minimum,
        float $maximum
    ): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->numeric($value, $path, $minimum, $maximum);
    }

    private function ruleId(mixed $id, int $index): string
    {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $id);

        return $id !== '' ? substr($id, 0, 80) : 'rule-'.($index + 1);
    }

    private function numberVariable(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'number'];
    }

    private function booleanVariable(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'boolean'];
    }

    private function textVariable(string $key, string $label, array $options = []): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'text', 'options' => $options];
    }

    private function fixedRule(
        string $id,
        string $label,
        string $field,
        string $operator,
        mixed $value,
        float $score
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'match' => 'all',
            'conditions' => [[
                'field' => $field,
                'operator' => $operator,
                'compare_with' => 'value',
                'value' => $value,
            ]],
            'score' => $this->fixedOutcome($score),
        ];
    }

    private function formulaRule(
        string $id,
        string $label,
        array $conditions,
        array $terms,
        float $constant,
        ?float $minimum,
        ?float $maximum
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'match' => 'all',
            'conditions' => $conditions,
            'score' => $this->formulaOutcome($terms, $constant, $minimum, $maximum),
        ];
    }

    private function fixedOutcome(float $value): array
    {
        return ['type' => 'fixed', 'value' => $value];
    }

    private function formulaOutcome(
        array $terms,
        float $constant,
        ?float $minimum,
        ?float $maximum
    ): array {
        return [
            'type' => 'formula',
            'terms' => $terms,
            'constant' => $constant,
            'minimum' => $minimum,
            'maximum' => $maximum,
        ];
    }

    private function fail(string $path, string $message): never
    {
        throw ValidationException::withMessages([
            "rule_configuration.{$path}" => [$message],
        ]);
    }
}
