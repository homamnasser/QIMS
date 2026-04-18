<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class UpdateSubjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'min_marks' => 'sometimes|integer|min:0',
            'max_marks' => 'sometimes|integer|gt:min_marks',
            'course_id' => 'sometimes|exists:courses,id',
            'shared_with_subject_id' => 'sometimes|nullable|exists:subjects,id',
            'pdf' => 'sometimes|file|mimes:pdf',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'Subject name must be a string.',
            'max_marks.gt' => 'The maximum marks must be greater than the minimum marks.',
            'course_id.exists' => 'The selected course does not exist.',
            'pdf.mimes' => 'The file must be a PDF document.',
            'pdf.max' => 'The PDF size should not exceed 10MB.',
            'shared_with_subject_id.exists' => 'The selected shared subject does not exist.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message' => $validator->errors(),
        ], 422));
    }
}
