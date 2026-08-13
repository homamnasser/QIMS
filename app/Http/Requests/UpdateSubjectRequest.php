<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSubjectRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'min_marks' => 'sometimes|integer|min:0|max:100',
            'max_marks' => 'sometimes|integer|min:0|max:100|gt:min_marks',
            'course_id' => 'sometimes|exists:courses,id',
            'shared_with_subject_id' => 'sometimes|nullable|exists:subjects,id',
            'pdf' => 'sometimes|file|mimes:pdf',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'اسم المادة الدراسية يجب أن يكون نصاً.',
            'min_marks.integer' => 'الدرجة الصغرى يجب أن تكون عدداً صحيحاً.',
            'min_marks.min' => 'الدرجة الصغرى يجب أن تكون 0 على الأقل.',
            'min_marks.max' => 'الدرجة الصغرى لا يمكن أن تتجاوز 100.',
            'max_marks.integer' => 'الدرجة العظمى يجب أن تكون عدداً صحيحاً.',
            'max_marks.min' => 'الدرجة العظمى يجب أن تكون 0 على الأقل.',
            'max_marks.max' => 'الدرجة العظمى لا يمكن أن تتجاوز 100.',
            'max_marks.gt' => 'الدرجة العظمى يجب أن تكون أكبر من الدرجة الصغرى.',
            'course_id.exists' => 'الكورس المحدد غير موجود.',
            'pdf.mimes' => 'الملف يجب أن يكون مستند PDF.',
            'pdf.max' => 'حجم ملف PDF يجب ألا يتجاوز 10 ميغابايت.',
            'shared_with_subject_id.exists' => 'المادة المشتركة المحددة غير موجودة.',
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
