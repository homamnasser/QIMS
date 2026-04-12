<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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
                    $project = \App\Models\Project::find($value);

                    if ($project && !$project->is_active) {
                        $fail('The selected project is not active.');
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
            'name.string' => 'Course name must be a string.',
            'name.max' => 'Course name may not be greater than 255 characters.',

            'description.string' => 'Course description must be a string.',


            'mosque_id.exists' => 'The selected mosque does not exist.',

            'project_id.exists' => 'The selected project does not exist.',

            'start_date.date' => 'Start date must be a valid date.',

            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be after or equal to the start date.',


            'parent_course_id.exists' => 'The selected parent course does not exist.',
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
