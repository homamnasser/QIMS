<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitSurveyResponseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['answers', 'student_fields'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $decoded]);
                }
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'access_token' => ['required', 'string'],
            'answers' => ['sometimes', 'array', 'max:500'],
            'answers.*' => ['nullable'],
            'student_fields' => ['sometimes', 'array', 'max:100'],
            'student_fields.*' => ['nullable'],
            'files' => ['sometimes', 'array', 'max:50'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:10240'],
        ];
    }
}
