<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCourseDateRequest extends FormRequest
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
            'course_id'  => 'required|exists:courses,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'required|date|after:start_date',
            'days'       => 'required|array|min:1',
            'days.*'     => 'string|in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday',
            'excluded_dates'   => 'nullable|array',
            'excluded_dates.*' => 'date|date_format:Y-m-d',
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'Course ID is required.',
            'course_id.exists' => 'The selected course does not exist.',

            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.after_or_equal' => 'Start date must be today or a future date.',

            'end_date.required' => 'End date is required.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after' => 'End date must be after the start date.',

            'days.required' => 'At least one day must be selected.',
            'days.array' => 'Days must be an array.',
            'days.min' => 'At least one day must be selected.',
            'days.*.string' => 'Each day must be a string.',
            'days.*.in' => 'Each day must be a valid day of the week (Saturday, Sunday, Monday, Tuesday, Wednesday, Thursday, Friday).',

            'excluded_dates.array' => 'Excluded dates must be an array.',
            'excluded_dates.*.date' => 'Each excluded date must be a valid date.',
            'excluded_dates.*.date_format' => 'Each excluded date must be in the format YYYY-MM-DD.',

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
