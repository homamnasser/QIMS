<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class RemoveStudentRequest extends FormRequest
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
            'circle_id'  => 'required|integer|exists:circles,id',
            'student_id' => 'required|integer|exists:students,id',
        ];
    }

    public function messages(): array
    {
        return [
            'circle_id.required' => 'The circle_id field is required.',
            'circle_id.integer' => 'The circle_id must be an integer.',
            'circle_id.exists' => 'The specified circle_id does not exist.',
            'student_id.required' => 'The student_id field is required.',
            'student_id.integer' => 'The student_id must be an integer.',
            'student_id.exists' => 'The specified student_id does not exist.',
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
