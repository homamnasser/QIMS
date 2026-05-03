<?php

namespace App\Http\Requests;

use App\Models\CourseDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AssignLessonsRequest extends FormRequest
{
    /**
     * تحديد الصلاحيات - نضعها true للسماح بالطلب
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق (Validation Rules)
     */
    public function rules(): array
    {
        $courseDateId = $this->input('course_date_id');

        // جلب الـ course_id الخاص بهذا اليوم لضمان عدم تكرار الدرس في نفس الدورة
        $courseId = CourseDate::where('id', $courseDateId)->value('course_id');

        return [
            'course_date_id' => 'required|integer|exists:course_dates,id',
            'lesson_ids'     => 'required|array|min:1',
            'lesson_ids.*'   => [
                'integer',
                'distinct',
                'exists:lessons,id',
                // التحقق المخصص لمنع تكرار الدرس داخل أيام نفس الدورة
                function ($attribute, $value, $fail) use ($courseId, $courseDateId) {
                    $exists = DB::table('course_date_lesson')
                        ->join('course_dates', 'course_date_lesson.course_date_id', '=', 'course_dates.id')
                        ->where('course_dates.course_id', $courseId)
                        ->where('course_date_lesson.lesson_id', $value)
                        // يسمح بالدرس إذا كان في نفس اليوم الحالي (حالة التحديث) ويمنعه إذا كان في يوم آخر
                        ->where('course_date_lesson.course_date_id', '!=', $courseDateId)
                        ->exists();

                    if ($exists) {
                        $fail("The lesson (ID: {$value}) has already been assigned to another date in this course curriculum.");
                    }
                },
            ],
        ];
    }

    /**
     * رسائل الخطأ باللغة الإنجليزية
     */
    public function messages(): array
    {
        return [
            'course_date_id.required' => 'The course date ID is required.',
            'course_date_id.exists'   => 'The selected course date does not exist.',
            'lesson_ids.required'     => 'At least one lesson must be selected.',
            'lesson_ids.array'        => 'The lessons must be provided as an array.',
            'lesson_ids.min'          => 'You must select at least one lesson.',
            'lesson_ids.*.integer'    => 'Each lesson ID must be a valid number.',
            'lesson_ids.*.distinct'   => 'Each lesson must be selected only once in this request.',
            'lesson_ids.*.exists'     => 'One or more selected lessons do not exist in our records.',
        ];
    }

    /**
     * معالجة فشل التحقق لإعادة رد JSON احترافي
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'status'  => 'error',
            'errors'  => $validator->errors()
        ], 422));
    }
}
