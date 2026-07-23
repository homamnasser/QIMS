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
            'type.required' => 'حقل النوع مطلوب.',
            'type.in'       => 'القيمة المحددة لحقل ... غير صالحة.',

            'note.string' => 'الملاحظة يجب أن تكون نصاً.',
            'note.max'    => 'الملاحظة يجب ألا تتجاوز 255 حرفاً.',

            'date.required' => 'حقل التاريخ مطلوب.',
            'date.date'     => 'التاريخ ليس بصيغة تاريخ صالحة.',
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
                    $validator->errors()->add('date', 'التاريخ المحدد ليس تاريخ جلسة صالحاً لهذا الكورس.');
                }

                $absenceExists = DB::table('student_course_absences')
                    ->where('student', $currentAbsence->student)
                    ->where('course', $currentAbsence->course)
                    ->where('date', $date)
                    ->where('id', '!=', $absenceId)
                    ->exists();

                if ($absenceExists) {
                    $validator->errors()->add('date', 'يوجد سجل غياب مسبق لهذا الطالب في التاريخ المحدد.');
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
