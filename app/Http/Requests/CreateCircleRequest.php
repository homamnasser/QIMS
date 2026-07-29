<?php

namespace App\Http\Requests;

use App\Enums\CircleQuranMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateCircleRequest extends FormRequest
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
            'name' => 'required|unique:circles|string|max:255',
            'teacher_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
            'quran_mode' => ['required', Rule::enum(CircleQuranMode::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الحلقة الدراسية مطلوب.',
            'name.string' => 'اسم الحلقة الدراسية يجب أن يكون نصاً.',
            'name.max' => 'اسم الحلقة الدراسية يجب ألا يتجاوز 255 حرفاً.',
            'name.unique' => 'يوجد حلقة دراسية بهذا الاسم مسبقاً.',
            'teacher_id.required' => 'معرّف المعلم مطلوب.',
            'teacher_id.exists' => 'المعلم المحدد غير موجود.',
            'course_id.required' => 'معرّف الكورس مطلوب.',
            'course_id.exists' => 'الكورس المحدد غير موجود.',
            'quran_mode.required' => 'يجب تحديد برنامج القرآن في الحلقة.',
            'quran_mode.enum' => 'برنامج القرآن المحدد غير صالح.',
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
