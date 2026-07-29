<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class StoreMemorizationRequest extends FormRequest
{
    /**
     * تحديد صلاحية تمرير الطلب
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
        return [
            'student_id' => 'required|integer|exists:students,id',
            'circle_id' => 'required|integer|exists:circles,id',
            'course_id' => 'nullable|integer|exists:courses,id',
            'course_date_id' => 'nullable|integer|exists:course_dates,id|required_if:record_type,revision',
            'record_type' => 'nullable|string|in:memorization,revision',
            'recorded_at' => 'nullable|date',
            'name' => 'nullable|string|max:255',
            'start_page' => 'required|integer|min:1|max:604',
            'end_page' => 'required|integer|min:1|max:604',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $studentId = $this->integer('student_id');
            $circleId = $this->integer('circle_id');
            $courseId = $this->integer('course_id');
            $courseDateId = $this->integer('course_date_id');

            if ($circleId) {
                $circle = DB::table('circles')->where('id', $circleId)->first();
                $isEnrolled = DB::table('student_circles')
                    ->where('student', $studentId)
                    ->where('circle', $circleId)
                    ->exists();
                if (! $isEnrolled) {
                    $validator->errors()->add('circle_id', 'الطالب غير مسجل في الحلقة المحددة.');
                }
                if ($circle?->quran_mode === 'none') {
                    $validator->errors()->add('circle_id', 'لا يمكن تسجيل تسميع لحلقة دروس عامة بلا قرآن.');
                }
                if ($courseId && $circle && (int) $circle->course_id !== $courseId) {
                    $validator->errors()->add('course_id', 'المقرر لا يتبع الحلقة المحددة.');
                }
            }

            if ($courseDateId) {
                $courseDate = DB::table('course_dates')->where('id', $courseDateId)->first();
                $resolvedCourseId = $courseId;
                if (! $resolvedCourseId && $circleId) {
                    $resolvedCourseId = (int) DB::table('circles')
                        ->where('id', $circleId)
                        ->value('course_id');
                }
                if ($resolvedCourseId
                    && $courseDate
                    && (int) $courseDate->course_id !== $resolvedCourseId) {
                    $validator->errors()->add('course_date_id', 'يوم الدوام لا يتبع مقرر الحلقة.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'معرّف الطالب مطلوب.',
            'student_id.integer' => 'معرّف الطالب يجب أن يكون عدداً صحيحاً.',
            'student_id.exists' => 'الطالب المحدد غير موجود.',
            'circle_id.required' => 'يجب تحديد الحلقة لربط التسميع بمصدره الصحيح.',
            'course_date_id.required_if' => 'يجب تحديد يوم الدوام عند تسجيل صفحة مراجعة.',
            'record_type.in' => 'نوع سجل القرآن يجب أن يكون حفظًا جديدًا أو مراجعة.',
            'name.string' => 'الاسم يجب أن يكون نصاً.',
            'name.max' => 'الاسم يجب ألا يتجاوز 255 حرفاً.',
            'start_page.required' => 'رقم صفحة البداية مطلوب.',
            'start_page.integer' => 'رقم صفحة البداية يجب أن يكون عدداً صحيحاً.',
            'start_page.min' => 'رقم صفحة البداية يجب أن يكون 1 على الأقل.',
            'start_page.max' => 'رقم صفحة البداية يجب ألا يتجاوز 604.',
            'end_page.required' => 'رقم صفحة النهاية مطلوب.',
            'end_page.integer' => 'رقم صفحة النهاية يجب أن يكون عدداً صحيحاً.',
            'end_page.min' => 'رقم صفحة النهاية يجب أن يكون 1 على الأقل.',
            'end_page.max' => 'رقم صفحة النهاية يجب ألا يتجاوز 604.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 422,
            'status' => 'error',
            'errors' => $validator->errors(),
        ], 422));
    }
}
