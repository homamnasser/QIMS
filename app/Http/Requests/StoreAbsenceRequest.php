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
            'student.required' => 'حقل الطالب مطلوب.',
            'student.integer'  => 'حقل الطالب يجب أن يكون عدداً صحيحاً.',
            'student.exists'   => 'الطالب المحدد غير موجود.',

            'course.required' => 'حقل الكورس مطلوب.',
            'course.integer'  => 'حقل الكورس يجب أن يكون عدداً صحيحاً.',
            'course.exists'   => 'الكورس المحدد غير موجود.',

            'note.string' => 'الملاحظة يجب أن تكون نصاً.',
            'note.max'    => 'الملاحظة يجب ألا تتجاوز 255 حرفاً.',

            'type.required' => 'حقل النوع مطلوب.',
            'type.in'       => 'القيمة المحددة لحقل ... غير صالحة.',

            'date.required' => 'حقل التاريخ مطلوب.',
            'date.date'     => 'التاريخ ليس بصيغة تاريخ صالحة.',
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
                $validator->errors()->add('student', 'الطالب المحدد غير مسجّل في أي حلقة تابعة لهذا الكورس.');
            }

            $isValidDate = DB::table('course_dates')
                ->where('course_id', $courseId)
                ->where('session_date', $date)
                ->exists();

            if (!$isValidDate) {
                $validator->errors()->add('date', 'التاريخ المحدد ليس تاريخ جلسة صالحاً لهذا الكورس.');
            }

            $absenceExists = DB::table('student_course_absences')
                ->where('student', $studentId)
                ->where('course', $courseId)
                ->where('date', $date)
                ->exists();

            if ($absenceExists) {
                $validator->errors()->add('date', 'يوجد سجل غياب مسبق لهذا الطالب في التاريخ المحدد.');
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
