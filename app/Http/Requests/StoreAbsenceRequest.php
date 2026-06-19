<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class StoreAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student' => 'required|integer|exists:students,id',
            'course'  => 'required|integer|exists:courses,id',
            'note'    => 'nullable|string|max:255',
            'type'    => 'required|in:present,full,first_period,second_period',
            'date'    => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'student.required' => 'The student field is required.',
            'student.integer'  => 'The student must be an integer.',
            'student.exists'   => 'The selected student does not exist.',

            'course.required' => 'The course field is required.',
            'course.integer'  => 'The course must be an integer.',
            'course.exists'   => 'The selected course does not exist.',

            'note.string' => 'The note must be a string.',
            'note.max'    => 'The note may not be greater than 255 characters.',

            'type.required' => 'The type field is required.',
            'type.in'       => 'The selected type is invalid. It must be one of: present,full, first_period, second_period.',

            'date.required' => 'The date field is required.',
            'date.date'     => 'The date is not a valid date format.',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $studentId = $this->input('student');
            $courseId  = $this->input('course');
            $date      = $this->input('date');

            if (!$studentId || !$courseId || !$date) {
                return;
            }

            $studentInCourse = DB::table('student_circles')
                ->join('circles', 'student_circles.circle', '=', 'circles.id')
                ->where('student_circles.student', $studentId)
                ->where('circles.course_id', $courseId)
                ->exists();

            if (!$studentInCourse) {
                $validator->errors()->add('student', 'The selected student is not registered in any circle belonging to this course.');
            }

            $isValidDate = DB::table('course_dates')
                ->where('course_id', $courseId)
                ->where('session_date', $date)
                ->exists();

            if (!$isValidDate) {
                $validator->errors()->add('date', 'The selected date is not a valid session date for this course.');
            }

            $absenceExists = DB::table('student_course_absences')
                ->where('student', $studentId)
                ->where('course', $courseId)
                ->where('date', $date)
                ->exists();

            if ($absenceExists) {
                $validator->errors()->add('date', 'An absence record already exists for this student on the selected date.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message' => $validator->errors(),
        ], 422));
    }
}
