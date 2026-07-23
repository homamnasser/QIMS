<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use App\Models\User;

class StoreCourseRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'mosque_id' => 'required|exists:mosques,id',
            'project_id' => [
                'required',
                'exists:projects,id',
                function ($attribute, $value, $fail) {
                    $project = \App\Models\Project::find($value);
                    if ($project && !$project->is_active) {
                        $fail('المشروع المحدد غير فعّال.');
                    }
                },
            ],
            'supervisor_id' => [
                'required',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    $user = User::find($value);

                    if ($user && !$user->canSupervise()) {
                        $fail('المستخدم المحدد يجب أن يملك صلاحية الإشراف.');
                    }
                },
            ],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'parent_course_id' => 'nullable|exists:courses,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم الكورس مطلوب.',
            'name.string' => 'اسم الكورس يجب أن يكون نصاً.',
            'name.max' => 'اسم الكورس يجب ألا يتجاوز 255 حرفاً.',

            'description.required' => 'وصف الكورس مطلوب.',
            'description.string' => 'وصف الكورس يجب أن يكون نصاً.',

            'mosque_id.required' => 'يجب اختيار مسجد للكورس.',
            'mosque_id.exists' => 'المسجد المحدد غير موجود.',

            'project_id.required' => 'يجب اختيار مشروع للكورس.',
            'project_id.exists' => 'المشروع المحدد غير موجود.',

            'supervisor_id.required' => 'يجب تعيين مشرف للكورس.',
            'supervisor_id.exists' => 'المشرف المحدد غير موجود.',

            'start_date.required' => 'تاريخ البدء مطلوب.',
            'start_date.date' => 'تاريخ البدء يجب أن يكون تاريخاً صالحاً.',

            'end_date.required' => 'تاريخ الانتهاء مطلوب.',
            'end_date.date' => 'تاريخ الانتهاء يجب أن يكون تاريخاً صالحاً.',
            'end_date.after_or_equal' => 'تاريخ الانتهاء يجب أن يكون بعد أو مساوياً لتاريخ البدء.',

            'parent_course_id.exists' => 'الكورس الأب المحدد غير موجود.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message' => $validator->errors(),
        ], 422));
    }
}
