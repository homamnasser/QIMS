<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'circle_id' => 'required|integer|exists:circles,id',
            'student_id' => 'required|integer|exists:students,id',
        ];
    }

    public function messages(): array
    {
        return [
            'circle_id.required' => 'حقل معرّف الحلقة مطلوب.',
            'circle_id.integer' => 'معرّف الحلقة يجب أن يكون عدداً صحيحاً.',
            'circle_id.exists' => 'معرّف الحلقة المحدد غير موجود.',
            'student_id.required' => 'حقل معرّف الطالب مطلوب.',
            'student_id.integer' => 'معرّف الطالب يجب أن يكون عدداً صحيحاً.',
            'student_id.exists' => 'معرّف الطالب المحدد غير موجود.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 422,
            'status' => 'error',
            'errors' => $validator->errors(),
        ], 422));
    }
}
