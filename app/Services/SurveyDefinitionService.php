<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SurveyDefinitionService
{
    public function __construct(private readonly SurveyStudentFieldRegistry $fieldRegistry) {}

    public function save(Survey $survey, array $definition, User $user): Survey
    {
        $allowedFields = collect($this->fieldRegistry->availableForUser($user))->pluck('key');
        foreach ($definition['student_fields'] ?? [] as $index => $field) {
            if (! $allowedFields->contains($field['field_key'])) {
                throw ValidationException::withMessages([
                    "student_fields.$index.field_key" => 'هذا الحقل غير متاح أو يحتاج إلى صلاحية عرض تفاصيل الطالب.',
                ]);
            }
        }

        DB::transaction(function () use ($survey, $definition): void {
            $survey->logicRules()->delete();
            SurveyQuestion::query()->where('survey_id', $survey->id)->delete();
            $survey->sections()->delete();
            $survey->studentFields()->delete();

            $sectionMap = [];
            $questionMap = [];

            foreach ($definition['sections'] as $sectionIndex => $sectionData) {
                $section = $survey->sections()->create([
                    'title' => $sectionData['title'],
                    'description' => $sectionData['description'] ?? null,
                    'display_order' => $sectionIndex,
                ]);
                $sectionMap[$sectionData['client_id']] = $section;

                foreach ($sectionData['questions'] ?? [] as $questionIndex => $questionData) {
                    $question = $survey->questions()->create([
                        'section_id' => $section->id,
                        'type' => $questionData['type'],
                        'title' => $questionData['title'],
                        'description' => $questionData['description'] ?? null,
                        'is_required' => (bool) ($questionData['is_required'] ?? false),
                        'display_order' => $questionIndex,
                        'validation_rules' => $questionData['validation_rules'] ?? [],
                        'settings' => $questionData['settings'] ?? [],
                    ]);
                    $questionMap[$questionData['client_id']] = $question;

                    foreach ($questionData['options'] ?? [] as $optionIndex => $optionData) {
                        $question->options()->create([
                            'label' => $optionData['label'],
                            'value' => $optionData['value'],
                            'display_order' => $optionIndex,
                        ]);
                    }
                }
            }

            foreach ($definition['student_fields'] ?? [] as $index => $fieldData) {
                $fieldDefinition = $this->fieldRegistry->definition($fieldData['field_key']);
                $survey->studentFields()->create([
                    'field_key' => $fieldData['field_key'],
                    'label' => $fieldData['label'] ?? $fieldDefinition['label'],
                    'mode' => $fieldData['mode'] ?? 'display',
                    'is_required' => (bool) ($fieldData['is_required'] ?? false),
                    'display_order' => $index,
                ]);
            }

            foreach ($definition['logic_rules'] ?? [] as $ruleData) {
                $targetType = $ruleData['target_type'];
                $survey->logicRules()->create([
                    'source_question_id' => $questionMap[$ruleData['source_client_id']]->id,
                    'target_type' => $targetType,
                    'target_question_id' => $targetType === 'question'
                        ? $questionMap[$ruleData['target_client_id']]->id
                        : null,
                    'target_section_id' => $targetType === 'section'
                        ? $sectionMap[$ruleData['target_client_id']]->id
                        : null,
                    'action' => $ruleData['action'],
                    'operator' => $ruleData['operator'] ?? 'contains',
                    'values' => array_values($ruleData['values']),
                ]);
            }
        });

        return $this->load($survey->refresh());
    }

    public function load(Survey $survey): Survey
    {
        return $survey->load([
            'creator:id,first_name,last_name',
            'sections.questions.options',
            'studentFields',
            'logicRules',
        ])->loadCount('responses');
    }

    public function serialize(Survey $survey, bool $includeDefinition = true): array
    {
        $data = [
            'id' => $survey->id,
            'name' => $survey->name,
            'description' => $survey->description,
            'status' => $survey->status,
            'logo_url' => $survey->logo_path ? asset('storage/'.ltrim($survey->logo_path, '/')) : null,
            'starts_at' => $survey->starts_at?->toIso8601String(),
            'ends_at' => $survey->ends_at?->toIso8601String(),
            'allow_multiple_responses' => $survey->allow_multiple_responses,
            'settings' => $survey->settings ?? [],
            'public_url' => $survey->publicUrl(),
            'preview_url' => $survey->previewUrl(),
            'published_at' => $survey->published_at?->toIso8601String(),
            'created_at' => $survey->created_at?->toIso8601String(),
            'responses_count' => $survey->responses_count ?? $survey->responses()->count(),
            'creator' => $survey->relationLoaded('creator') && $survey->creator ? [
                'id' => $survey->creator->id,
                'name' => trim($survey->creator->first_name.' '.$survey->creator->last_name),
            ] : null,
            'mosque_id' => $survey->mosque_id,
        ];

        if (! $includeDefinition) {
            return $data;
        }

        $data['student_fields'] = $survey->studentFields->map(function ($field): array {
            $definition = $this->fieldRegistry->definition($field->field_key);

            return [
                'id' => $field->id,
                'field_key' => $field->field_key,
                'label' => $field->label,
                'mode' => $field->mode,
                'is_required' => $field->is_required,
                'type' => $definition['type'] ?? 'text',
            ];
        })->values()->all();

        $data['sections'] = $survey->sections->map(fn ($section): array => [
            'id' => $section->id,
            'client_id' => 'section-'.$section->id,
            'title' => $section->title,
            'description' => $section->description,
            'display_order' => $section->display_order,
            'questions' => $section->questions->map(fn ($question): array => [
                'id' => $question->id,
                'client_id' => 'question-'.$question->id,
                'question_key' => $question->question_key,
                'type' => $question->type,
                'title' => $question->title,
                'description' => $question->description,
                'is_required' => $question->is_required,
                'display_order' => $question->display_order,
                'validation_rules' => $question->validation_rules ?? [],
                'settings' => $question->settings ?? [],
                'options' => $question->options->map(fn ($option): array => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'value' => $option->value,
                    'display_order' => $option->display_order,
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all();

        $data['logic_rules'] = $survey->logicRules->map(fn ($rule): array => [
            'id' => $rule->id,
            'source_question_id' => $rule->source_question_id,
            'source_client_id' => 'question-'.$rule->source_question_id,
            'target_type' => $rule->target_type,
            'target_id' => $rule->target_type === 'question' ? $rule->target_question_id : $rule->target_section_id,
            'target_client_id' => ($rule->target_type === 'question' ? 'question-' : 'section-')
                .($rule->target_type === 'question' ? $rule->target_question_id : $rule->target_section_id),
            'action' => $rule->action,
            'operator' => $rule->operator,
            'values' => $rule->values,
        ])->values()->all();

        return $data;
    }

    public function publicationErrors(Survey $survey): array
    {
        $survey = $this->load($survey);
        $errors = [];

        if (! $survey->logo_path) {
            $errors[] = 'يجب إضافة شعار أو صورة للاستبيان قبل نشره.';
        }
        if ($survey->starts_at && $survey->ends_at && $survey->ends_at->lte($survey->starts_at)) {
            $errors[] = 'تاريخ نهاية الاستبيان يجب أن يكون بعد تاريخ البداية.';
        }
        if ($survey->questions->isEmpty() && $survey->studentFields->isEmpty()) {
            $errors[] = 'يجب إضافة حقل طالب أو سؤال واحد على الأقل قبل النشر.';
        }

        foreach ($survey->questions as $question) {
            if (! in_array($question->type, config('surveys.question_types'), true)) {
                $errors[] = "نوع السؤال «{$question->title}» غير مدعوم.";
            }
            if (in_array($question->type, ['radio', 'checkbox'], true) && $question->options->count() < 2) {
                $errors[] = "السؤال «{$question->title}» يحتاج إلى خيارين على الأقل.";
            }
        }

        $errors = [...$errors, ...$this->logicErrors($survey)];

        return array_values(array_unique($errors));
    }

    private function logicErrors(Survey $survey): array
    {
        $errors = [];
        $questionById = $survey->questions->keyBy('id');
        $positions = [];
        foreach ($survey->sections as $section) {
            foreach ($section->questions as $question) {
                $positions[$question->id] = ($section->display_order * 100000) + $question->display_order;
            }
        }

        $graph = [];
        foreach ($survey->logicRules as $rule) {
            $source = $questionById->get($rule->source_question_id);
            $targets = $rule->target_type === 'question'
                ? collect([$questionById->get($rule->target_question_id)])->filter()
                : $survey->sections->firstWhere('id', $rule->target_section_id)?->questions ?? collect();

            if (! $source || $targets->isEmpty()) {
                $errors[] = 'يوجد شرط منطقي يشير إلى سؤال أو قسم غير موجود.';

                continue;
            }

            $validValues = $source->options->pluck('value');
            if ($source->options->isNotEmpty() && collect($rule->values)->diff($validValues)->isNotEmpty()) {
                $errors[] = "الشرط المرتبط بالسؤال «{$source->title}» يحتوي على خيار غير صالح.";
            }

            foreach ($targets as $target) {
                if ($source->id === $target->id) {
                    $errors[] = "لا يمكن أن يعتمد السؤال «{$source->title}» على نفسه.";

                    continue;
                }
                if (($positions[$source->id] ?? 0) >= ($positions[$target->id] ?? PHP_INT_MAX)) {
                    $errors[] = "يجب أن يسبق السؤال المصدر «{$source->title}» السؤال أو القسم التابع له.";
                }
                $graph[$source->id][] = $target->id;
            }
        }

        if ($this->hasCycle($graph)) {
            $errors[] = 'توجد حلقة دائرية في شروط الاستبيان. أزل الاعتماد المتبادل بين الأسئلة.';
        }

        return $errors;
    }

    private function hasCycle(array $graph): bool
    {
        $visited = [];
        $active = [];

        $visit = function (int $node) use (&$visit, &$visited, &$active, $graph): bool {
            if ($active[$node] ?? false) {
                return true;
            }
            if ($visited[$node] ?? false) {
                return false;
            }
            $visited[$node] = true;
            $active[$node] = true;
            foreach ($graph[$node] ?? [] as $next) {
                if ($visit($next)) {
                    return true;
                }
            }
            $active[$node] = false;

            return false;
        };

        foreach (array_keys($graph) as $node) {
            if ($visit((int) $node)) {
                return true;
            }
        }

        return false;
    }
}
