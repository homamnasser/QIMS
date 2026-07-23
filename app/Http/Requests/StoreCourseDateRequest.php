<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCourseDateRequest extends FormRequest
{
    /**
     * الحقلين سيتوفران للاستخدام في الكنترولر بعد التحقق
     */
    public $course_start_date;
    public $course_end_date;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * تعديل منطق التحقق لجلب التواريخ من الكورس
     */
    public function rules(): array
    {
        // جلب بيانات الكورس من قاعدة البيانات بناءً على الـ ID المرسل
        $course = Course::find($this->course_id);

        if ($course) {
            $this->course_start_date = $course->start_date;
            $this->course_end_date = $course->end_date;
        }

        return [
            'course_id'  => 'required|exists:courses,id',
            'days'       => 'required|array|min:1',
            'days.*'     => 'string|in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday',
            'excluded_dates'   => 'nullable|array',
            'excluded_dates.*' => [
                'date',
                'date_format:Y-m-d',
                function ($attribute, $value, $fail) use ($course) {
                    if ($course) {
                        if ($value < $course->start_date || $value > $course->end_date) {
                            $fail("التاريخ المستبعد $value يجب أن يكون بين تواريخ الكورس ({$course->start_date} و {$course->end_date}).");
                        }
                    }
                }
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'معرّف الكورس مطلوب.',
            'course_id.exists' => 'الكورس المحدد غير موجود.',
            'days.required' => 'يجب اختيار يوم واحد على الأقل.',
            'days.*.in' => 'كل يوم يجب أن يكون يوماً صالحاً من أيام الأسبوع.',
            'excluded_dates.*.date_format' => 'كل تاريخ مستبعد يجب أن يكون بصيغة YYYY-MM-DD.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message' => $validator->errors(),
        ], 422));
    }
}
