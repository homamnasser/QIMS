<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'required|exists:courses,id',
            'min_marks' => 'required|integer|min:0|max:100',
            'max_marks' => 'required|integer|min:0|max:100|gt:min_marks',
            'shared_with_subject_id' => 'nullable|exists:subjects,id',
            'pdf' => 'nullable|file|mimes:pdf',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المادة الدراسية مطلوب.',
            'name.string' => 'اسم المادة الدراسية يجب أن يكون نصاً.',
            'name.max' => 'اسم المادة الدراسية يجب ألا يتجاوز 255 حرفاً.',

            'course_id.required' => 'معرّف الكورس مطلوب.',
            'course_id.exists' => 'الكورس المحدد غير موجود.',

            'min_marks.required' => 'الدرجة الصغرى مطلوبة.',
            'min_marks.integer' => 'الدرجة الصغرى يجب أن تكون عدداً صحيحاً.',
            'min_marks.min' => 'الدرجة الصغرى يجب أن تكون 0 على الأقل.',
            'min_marks.max' => 'الدرجة الصغرى لا يمكن أن تتجاوز 100.',

            'max_marks.required' => 'الدرجة العظمى مطلوبة.',
            'max_marks.integer' => 'الدرجة العظمى يجب أن تكون عدداً صحيحاً.',
            'max_marks.min' => 'الدرجة العظمى يجب أن تكون 0 على الأقل.',
            'max_marks.max' => 'الدرجة العظمى لا يمكن أن تتجاوز 100.',
            'max_marks.gt' => 'الدرجة العظمى يجب أن تكون أكبر من الدرجة الصغرى.',

            'shared_with_subject_id.exists' => 'المادة المشتركة المحددة غير موجودة.',

            'pdf.file' => 'الملف المرفوع يجب أن يكون ملفاً صالحاً.',
            'pdf.mimes' => 'الملف يجب أن يكون من نوع: ...',

        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'code' => 422,
            'message' => $validator->errors(),
        ], 422));
    }
}
