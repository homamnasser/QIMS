<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class StoreMemorizationRequest extends FormRequest
{
    /**
     * تحديد صلاحية تمرير الطلب
     */
    public function authorize(): bool
    {
        return true; 
    }

    /**
     * قواعد التحقق (Validation Rules)
     */
    public function rules(): array
    {
        return [
            'student_id' => 'required|integer|exists:students,id',

            'name'       => 'nullable|string|max:255',

            'start_page' => 'required|integer|min:1|max:604',
            'end_page'   => 'required|integer|min:1|max:604',
        ];
    }


    public function messages(): array
    {
        return [
            'student_id.required' => 'The student ID is required.',
            'student_id.integer' => 'The student ID must be an integer.',
            'student_id.exists' => 'The specified student does not exist.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'start_page.required' => 'The start page number is required.',
            'start_page.integer' => 'The start page number must be an integer.',
            'start_page.min' => 'The start page number must be at least 1.',
            'start_page.max' => 'The start page number may not be greater than 604.',
            'end_page.required' => 'The end page number is required.',
            'end_page.integer' => 'The end page number must be an integer.',
            'end_page.min' => 'The end page number must be at least 1.',
            'end_page.max' => 'The end page number may not be greater than 604.',
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
