<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreWarningRequest extends FormRequest
{
    /**
     * تحديد صلاحية تمرير الطلب
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق لإنشاء إنذار 🎯
     */
    protected function prepareForValidation()
    {
        $this->getInputSource()->add([
            'warner' => auth()->id(),
        ]);

        $this->request->add([
            'warner' => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'student' => 'required|integer|exists:students,id',
            'warner' => 'required|integer|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'deduction_points' => 'nullable|numeric|min:1|max:5',
        ];
    }

    /**
     * رسائل التحقق المخصصة بستايل مشروعك الموحد
     */
    public function messages(): array
    {
        return [
            'student.required' => 'معرّف الطالب مطلوب.',
            'student.integer' => 'معرّف الطالب يجب أن يكون عدداً صحيحاً.',
            'student.exists' => 'الطالب المحدد غير موجود.',
            'title.required' => 'العنوان مطلوب.',
            'title.string' => 'العنوان يجب أن يكون نصاً.',
            'title.max' => 'العنوان يجب ألا يتجاوز 255 حرفاً.',
            'description.string' => 'الوصف يجب أن يكون نصاً.',
            'deduction_points.numeric' => 'حسم الإنذار يجب أن يكون رقمًا.',
            'deduction_points.min' => 'حسم الإنذار لا يقل عن درجة واحدة.',
            'deduction_points.max' => 'حسم الإنذار لا يزيد على خمس درجات.',
        ];
    }

    /**
     * معالجة الفشل بـ API JSON Response متناسق
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 422,
            'errors' => $validator->errors(),
        ], 422));
    }
}
