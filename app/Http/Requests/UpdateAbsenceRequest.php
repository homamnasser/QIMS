<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class UpdateAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:present,full,first_period,second_period',
            'note' => 'nullable|string|max:255',
            'date' => 'required|date',
        ];
    }
 public function messages(): array
    {
        return [
            'type.required' => 'The type field is required.',
            'type.in'       => 'The selected type is invalid. It must be one of: present,full, first_period, second_period.',

            'note.string' => 'The note must be a string.',
            'note.max'    => 'The note may not be greater than 255 characters.',

            'date.required' => 'The date field is required.',
            'date.date'     => 'The date is not a valid date format.',
        ];
    }
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $date      = $this->input('date');
            $absenceId = $this->route('id');

            if (!$date || !$absenceId) {
                return;
            }

            $currentAbsence = DB::table('student_course_absences')->where('id', $absenceId)->first();

            if ($currentAbsence) {
                $isValidDate = DB::table('course_dates')
                    ->where('course_id', $currentAbsence->course)
                    ->where('session_date', $date)
                    ->exists();

                if (!$isValidDate) {
                    $validator->errors()->add('date', 'The selected date is not a valid session date for this course.');
                }

                $absenceExists = DB::table('student_course_absences')
                    ->where('student', $currentAbsence->student)
                    ->where('course', $currentAbsence->course)
                    ->where('date', $date)
                    ->where('id', '!=', $absenceId)
                    ->exists();

                if ($absenceExists) {
                    $validator->errors()->add('date', 'An absence record already exists for this student on the selected date.');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'status'  => 'error',
            'message' => $validator->errors(),
        ], 422));
    }
}
