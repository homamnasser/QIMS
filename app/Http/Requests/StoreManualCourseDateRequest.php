<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreManualCourseDateRequest extends FormRequest
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
            'course_id' => 'required|exists:courses,id',
            'session_date' => [
                'required',
                'date',
                Rule::unique('course_dates')->where(function ($query) {
                    return $query->where('course_id', $this->course_id);
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'معرّف الكورس مطلوب.',
            'course_id.exists' => 'الكورس المحدد غير موجود.',

            'session_date.required' => 'تاريخ الجلسة مطلوب.',
            'session_date.date' => 'تاريخ الجلسة يجب أن يكون تاريخاً صالحاً.',
            'session_date.unique' => 'تاريخ الجلسة لهذا الكورس موجود مسبقاً.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 422,
            'message' => $validator->errors(),
        ], 422));
    }
}
