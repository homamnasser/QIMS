<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreSubjectRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'course_id' => 'required|exists:courses,id',
            'min_marks' => 'required|integer|min:0',
            'max_marks' => 'required|integer|gt:min_marks',
            'shared_with_subject_id' => 'nullable|exists:subjects,id',
            'pdf' => 'required|file|mimes:pdf'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Subject name is required.',
            'name.string' => 'Subject name must be a string.',
            'name.max' => 'Subject name may not be greater than 255 characters.',

            'course_id.required' => 'Course ID is required.',
            'course_id.exists' => 'The selected course does not exist.',

            'min_marks.required' => 'Minimum marks are required.',
            'min_marks.integer' => 'Minimum marks must be an integer.',
            'min_marks.min' => 'Minimum marks must be at least 0.',

            'max_marks.required' => 'Maximum marks are required.',
            'max_marks.integer' => 'Maximum marks must be an integer.',
            'max_marks.gt' => 'Maximum marks must be greater than minimum marks.',

            'shared_with_subject_id.exists' => 'The selected shared subject does not exist.',

            'pdf.file' => 'The uploaded file must be a valid file.',
            'pdf.mimes' => 'The uploaded file must be a PDF.',

        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message'  => $validator->errors()
        ], 422));
    }
}
