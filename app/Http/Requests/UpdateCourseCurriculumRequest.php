<?php

namespace App\Http\Requests;

use App\Models\CourseDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCourseCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


       public function rules(): array
    {
        $courseDate = $this->route('courseDate');
        $courseDateId = ($courseDate instanceof CourseDate) ? $courseDate->id : $courseDate;

        $courseId = CourseDate::where('id', $courseDateId)->value('course_id');

        return [
            'lesson_ids'   => 'required|array|min:1',
            'lesson_ids.*' => [
                'integer',
                'distinct',
                'exists:lessons,id',
                function ($attribute, $value, $fail) use ($courseId, $courseDateId) {
                    $exists = DB::table('course_date_lesson')
                        ->join('course_dates', 'course_date_lesson.course_date_id', '=', 'course_dates.id')
                        ->where('course_dates.course_id', $courseId)
                        ->where('course_date_lesson.lesson_id', $value)
                        ->where('course_date_lesson.course_date_id', '!=', $courseDateId)
                        ->exists();

                    if ($exists) {
                        $fail("الدرس (معرّف: {$value}) مُسنَد مسبقاً لتاريخ آخر في هذا الكورس.");
                    }
                },
            ],
        ];
    }


    public function messages(): array
    {
        return [
            'lesson_ids.required' => 'يجب إسناد درس واحد على الأقل لتاريخ الكورس.',
            'lesson_ids.array'    => 'حقل معرّفات الدروس يجب أن يكون مصفوفة.',
            'lesson_ids.min'      => 'يجب إسناد درس واحد على الأقل لتاريخ الكورس.',
            'lesson_ids.*.integer' => 'معرّف كل درس يجب أن يكون عدداً صحيحاً.',
            'lesson_ids.*.distinct' => 'لا يُسمح بتكرار معرّفات الدروس.',
            'lesson_ids.*.exists' => 'معرّف درس واحد أو أكثر غير موجود في النظام.',
        ];
    }


    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'status'  => 'error',
            'errors'  => $validator->errors()
        ], 422));
    }

}
