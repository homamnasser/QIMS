<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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
                        $fail('The selected project is not active.');
                    }
                },
            ],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'parent_course_id' => 'nullable|exists:courses,id',
        ];
    }
    public function messages(): array
    {
        return [
            'name.required' => 'Course name is required.',
            'name.string' => 'Course name must be a string.',
            'name.max' => 'Course name may not be greater than 255 characters.',

            'description.required' => 'Course description is required.',
            'description.string' => 'Course description must be a string.',


            'mosque_id.required' => 'A mosque must be selected for the course.',
            'mosque_id.exists' => 'The selected mosque does not exist.',

            'project_id.required' => 'A project must be selected for the course.',
            'project_id.exists' => 'The selected project does not exist.',
            'project_id.is_active' => 'The selected project is not active.',

            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date must be a valid date.',

            'end_date.required' => 'End date is required.',
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
