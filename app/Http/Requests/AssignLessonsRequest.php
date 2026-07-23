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
                        $fail("الدرس (معرّف: {$value}) مُسنَد مسبقاً لتاريخ آخر في هذا الكورس.");
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
            'course_date_id.required' => 'معرّف تاريخ الكورس مطلوب.',
            'course_date_id.exists'   => 'تاريخ الكورس المحدد غير موجود.',
            'lesson_ids.required'     => 'يجب اختيار درس واحد على الأقل.',
            'lesson_ids.array'        => 'يجب تقديم الدروس كمصفوفة.',
            'lesson_ids.min'          => 'يجب اختيار درس واحد على الأقل.',
            'lesson_ids.*.integer'    => 'معرّف كل درس يجب أن يكون رقماً صالحاً.',
            'lesson_ids.*.distinct'   => 'يجب اختيار كل درس مرة واحدة فقط في هذا الطلب.',
            'lesson_ids.*.exists'     => 'درس واحد أو أكثر من الدروس المحددة غير موجود في سجلاتنا.',
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
