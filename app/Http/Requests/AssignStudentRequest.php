<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class AssignStudentRequest extends FormRequest
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
            'circle_id'     => 'required|integer|exists:circles,id',
            'student_ids'   => 'required|array',
            'student_ids.*' => 'integer|exists:students,id',
        ];
    }

    public function messages(): array
    {
        return [
            'circle_id.required' => 'The circle_id field is required.',
            'circle_id.integer' => 'The circle_id must be an integer.',
            'circle_id.exists' => 'The specified circle_id does not exist.',
            'student_ids.array' => 'The student_ids must be an array.',
            'student_ids.*.integer' => 'Each student_id in student_ids must be an integer.',
            'student_ids.*.exists' => 'Each specified student_id in student_ids must exist.',
            'student_ids.required' => 'The student_ids field is required.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'status'  => 'error',
            'errors'  => $validator->errors()
        ], 422));
    }
}
