<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class StoreReadingImprovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student' => 'required|integer|exists:students,id',
            'course' => 'required|integer|exists:courses,id',
            'type' => 'required|in:significant_improvement,slight_improvement,no_improvement,decline',
            'occurred_at' => 'nullable|date',
            'description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * 🔐 التحقق من أن الطالب ينتمي للكورس المختار عبر حلقة بجدول student_circles
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $studentId = $this->input('student');
            $courseId = $this->input('course');

            if (! $studentId || ! $courseId) {
                return;
            }

            $isEnrolledInCourse = DB::table('student_circles')
                ->join('circles', 'student_circles.circle', '=', 'circles.id')
                ->where('student_circles.student', $studentId)
                ->where('circles.course_id', $courseId)
                ->exists();

            if (! $isEnrolledInCourse) {
                $validator->errors()->add('student', 'The selected student is not registered in any circle within this course.');
            }
        });
    }

    public function messages()
    {
        return [
            'student.required' => 'حقل الطالب مطلوب.',
            'student.integer' => 'حقل الطالب يجب أن يكون رقمًا صحيحًا.',
            'student.exists' => 'الطالب المحدد غير موجود.',
            'course.required' => 'حقل الكورس مطلوب.',
            'course.integer' => 'حقل الكورس يجب أن يكون رقمًا صحيحًا.',
            'course.exists' => 'الكورس المحدد غير موجود.',
            'type.required' => 'حقل نوع التحسن مطلوب.',
            'type.in' => 'نوع التحسن يجب أن يكون أحد القيم التالية: significant_improvement, slight_improvement, no_improvement, decline.',
            'description.string' => 'حقل الوصف يجب أن يكون نصًا.',
            'description.max' => 'حقل الوصف يجب ألا يتجاوز 1000 حرف.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 422,
            'message' => $validator->errors(),
        ], 422));
    }
}
