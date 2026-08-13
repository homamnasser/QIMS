<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreLessonRequest extends FormRequest
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
            'description' => 'required|string',
            'start_page' => 'required|integer|min:1',
            'end_page' => 'required|integer|gte:start_page',
            'subject_id' => 'required|exists:subjects,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الدرس مطلوب.',
            'name.string' => 'اسم الدرس يجب أن يكون نصاً.',
            'name.max' => 'اسم الدرس يجب ألا يتجاوز 255 حرفاً.',

            'description.required' => 'وصف الدرس مطلوب.',
            'description.string' => 'الوصف يجب أن يكون نصاً.',

            'start_page.required' => 'صفحة البداية مطلوبة.',
            'start_page.integer' => 'صفحة البداية يجب أن تكون عدداً صحيحاً.',
            'start_page.min' => 'صفحة البداية يجب أن تكون 1 على الأقل.',

            'end_page.required' => 'صفحة النهاية مطلوبة.',
            'end_page.integer' => 'صفحة النهاية يجب أن تكون عدداً صحيحاً.',
            'end_page.gte' => 'صفحة النهاية يجب أن تكون أكبر من أو تساوي صفحة البداية.',

            'subject_id.required' => 'يجب اختيار مادة دراسية للدرس.',
            'subject_id.exists' => 'المادة الدراسية المحددة غير موجودة.',

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
