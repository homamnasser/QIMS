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
            'circle_id.required' => 'حقل معرّف الحلقة مطلوب.',
            'circle_id.integer' => 'معرّف الحلقة يجب أن يكون عدداً صحيحاً.',
            'circle_id.exists' => 'معرّف الحلقة المحدد غير موجود.',
            'student_ids.array' => 'معرّفات الطلاب يجب أن تكون مصفوفة.',
            'student_ids.*.integer' => 'كل معرّف طالب يجب أن يكون عدداً صحيحاً.',
            'student_ids.*.exists' => 'كل معرّف طالب محدد يجب أن يكون موجوداً.',
            'student_ids.required' => 'حقل معرّفات الطلاب مطلوب.',
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
