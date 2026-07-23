<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateLessonRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'start_page' => 'sometimes|integer|min:1',
            'end_page' => 'sometimes|integer|gte:start_page',
            'subject_id' => 'sometimes|exists:subjects,id',
        ];
    }
    public function messages(): array
    {
        return [
            'name.string' => 'اسم الدرس يجب أن يكون نصاً.',
            'name.max' => 'اسم الدرس يجب ألا يتجاوز 255 حرفاً.',

            'description.string' => 'الوصف يجب أن يكون نصاً.',

            'start_page.integer' => 'صفحة البداية يجب أن تكون عدداً صحيحاً.',
            'start_page.min' => 'صفحة البداية يجب أن تكون 1 على الأقل.',

            'end_page.integer' => 'صفحة النهاية يجب أن تكون عدداً صحيحاً.',
            'end_page.gte' => 'صفحة النهاية يجب أن تكون أكبر من أو تساوي صفحة البداية.',

            'subject_id.exists' => 'المادة الدراسية المحددة غير موجودة.',

        ];
    }
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message' =>  $validator->errors(),
        ], 422));
    }
}
