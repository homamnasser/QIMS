<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
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
            'description' => 'sometimes|string',
            'mosque_id' => 'sometimes|exists:mosques,id',
            'project_id' => [
                'sometimes',
                'exists:projects,id',
                function ($attribute, $value, $fail) {
                    $project = Project::find($value);

                    if ($project && ! $project->is_active) {
                        $fail('المشروع المحدد غير فعّال.');
                    }
                },
            ],
            'supervisor_id' => [
                'sometimes',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    $user = User::find($value);

                    if ($user && ! $user->canSupervise()) {
                        $fail('المستخدم المحدد يجب أن يملك صلاحية الإشراف.');
                    }
                },
            ],
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'parent_course_id' => 'nullable|exists:courses,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'اسم الكورس يجب أن يكون نصاً.',
            'name.max' => 'اسم الكورس يجب ألا يتجاوز 255 حرفاً.',

            'description.string' => 'وصف الكورس يجب أن يكون نصاً.',

            'mosque_id.exists' => 'المسجد المحدد غير موجود.',

            'project_id.exists' => 'المشروع المحدد غير موجود.',

            'supervisor_id.exists' => 'المشرف المحدد غير موجود.',

            'start_date.date' => 'تاريخ البدء يجب أن يكون تاريخاً صالحاً.',

            'end_date.date' => 'تاريخ الانتهاء يجب أن يكون تاريخاً صالحاً.',
            'end_date.after_or_equal' => 'تاريخ الانتهاء يجب أن يكون بعد أو مساوياً لتاريخ البدء.',

            'parent_course_id.exists' => 'الكورس الأب المحدد غير موجود.',
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
