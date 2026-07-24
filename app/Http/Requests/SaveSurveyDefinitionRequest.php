<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSurveyDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sections' => ['required', 'array', 'min:1', 'max:100'],
            'sections.*.client_id' => ['required', 'string', 'max:80', 'distinct'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.description' => ['nullable', 'string', 'max:2000'],
            'sections.*.questions' => ['present', 'array', 'max:300'],
            'sections.*.questions.*.client_id' => ['required', 'string', 'max:80', 'distinct'],
            'sections.*.questions.*.type' => ['required', Rule::in(config('surveys.question_types'))],
            'sections.*.questions.*.title' => ['required', 'string', 'max:500'],
            'sections.*.questions.*.description' => ['nullable', 'string', 'max:2000'],
            'sections.*.questions.*.is_required' => ['sometimes', 'boolean'],
            'sections.*.questions.*.validation_rules' => ['sometimes', 'array'],
            'sections.*.questions.*.validation_rules.min_length' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'sections.*.questions.*.validation_rules.max_length' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'sections.*.questions.*.settings' => ['sometimes', 'array'],
            'sections.*.questions.*.options' => ['sometimes', 'array', 'max:200'],
            'sections.*.questions.*.options.*.label' => ['required_with:sections.*.questions.*.options', 'string', 'max:255'],
            'sections.*.questions.*.options.*.value' => ['required_with:sections.*.questions.*.options', 'string', 'max:255'],
            'student_fields' => ['sometimes', 'array', 'max:100'],
            'student_fields.*.field_key' => ['required', 'string', 'max:80', 'distinct'],
            'student_fields.*.label' => ['sometimes', 'required', 'string', 'max:255'],
            'student_fields.*.mode' => ['required', Rule::in(['display', 'editable'])],
            'student_fields.*.is_required' => ['sometimes', 'boolean'],
            'logic_rules' => ['sometimes', 'array', 'max:500'],
            'logic_rules.*.source_client_id' => ['required', 'string', 'max:80'],
            'logic_rules.*.target_type' => ['required', Rule::in(['question', 'section'])],
            'logic_rules.*.target_client_id' => ['required', 'string', 'max:80'],
            'logic_rules.*.action' => ['required', Rule::in(config('surveys.rule_actions'))],
            'logic_rules.*.operator' => ['sometimes', Rule::in(['equals', 'contains', 'in'])],
            'logic_rules.*.values' => ['required', 'array', 'min:1'],
            'logic_rules.*.values.*' => ['string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sections = collect($this->input('sections', []));
            $sectionIds = $sections->pluck('client_id');
            $questions = $sections->flatMap(fn (array $section) => $section['questions'] ?? []);
            $questionIds = $questions->pluck('client_id');

            if ($questionIds->duplicates()->isNotEmpty()) {
                $validator->errors()->add('sections', 'معرّفات الأسئلة الداخلية يجب أن تكون فريدة.');
            }

            foreach ($questions as $questionIndex => $question) {
                if (in_array($question['type'] ?? null, ['radio', 'checkbox'], true)
                    && count($question['options'] ?? []) < 2) {
                    $validator->errors()->add(
                        'sections',
                        'أسئلة الاختيار تحتاج إلى خيارين على الأقل.',
                    );
                }
                $optionValues = collect($question['options'] ?? [])->pluck('value');
                if ($optionValues->duplicates()->isNotEmpty()) {
                    $validator->errors()->add(
                        "questions.$questionIndex.options",
                        'قيم خيارات السؤال يجب أن تكون فريدة.',
                    );
                }

                $min = data_get($question, 'validation_rules.min_length');
                $max = data_get($question, 'validation_rules.max_length');
                if ($min !== null && $max !== null && (int) $max < (int) $min) {
                    $validator->errors()->add(
                        "questions.$questionIndex.validation_rules",
                        'الحد الأقصى للنص يجب ألا يقل عن الحد الأدنى.',
                    );
                }
            }

            foreach ($this->input('logic_rules', []) as $index => $rule) {
                if (! $questionIds->contains($rule['source_client_id'] ?? null)) {
                    $validator->errors()->add("logic_rules.$index.source_client_id", 'السؤال المصدر غير موجود.');
                }

                $targetIds = ($rule['target_type'] ?? null) === 'section' ? $sectionIds : $questionIds;
                if (! $targetIds->contains($rule['target_client_id'] ?? null)) {
                    $validator->errors()->add("logic_rules.$index.target_client_id", 'هدف الشرط غير موجود.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'sections.required' => 'يجب إضافة قسم واحد على الأقل.',
            'sections.*.title.required' => 'عنوان القسم مطلوب.',
            'sections.*.questions.*.title.required' => 'عنوان السؤال مطلوب.',
            'sections.*.questions.*.type.in' => 'نوع السؤال غير مدعوم.',
            'logic_rules.*.values.min' => 'يجب اختيار قيمة واحدة على الأقل للشرط.',
        ];
    }
}
