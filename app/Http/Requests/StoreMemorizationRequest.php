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
            'student_id.required' => 'معرّف الطالب مطلوب.',
            'student_id.integer' => 'معرّف الطالب يجب أن يكون عدداً صحيحاً.',
            'student_id.exists' => 'الطالب المحدد غير موجود.',
            'name.string' => 'الاسم يجب أن يكون نصاً.',
            'name.max' => 'الاسم يجب ألا يتجاوز 255 حرفاً.',
            'start_page.required' => 'رقم صفحة البداية مطلوب.',
            'start_page.integer' => 'رقم صفحة البداية يجب أن يكون عدداً صحيحاً.',
            'start_page.min' => 'رقم صفحة البداية يجب أن يكون 1 على الأقل.',
            'start_page.max' => 'رقم صفحة البداية يجب ألا يتجاوز 604.',
            'end_page.required' => 'رقم صفحة النهاية مطلوب.',
            'end_page.integer' => 'رقم صفحة النهاية يجب أن يكون عدداً صحيحاً.',
            'end_page.min' => 'رقم صفحة النهاية يجب أن يكون 1 على الأقل.',
            'end_page.max' => 'رقم صفحة النهاية يجب ألا يتجاوز 604.',
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
