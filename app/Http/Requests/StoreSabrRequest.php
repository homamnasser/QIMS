<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class StoreSabrRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 1. شروط الفحص الأساسية للحقول المطلوبة
     */
    public function rules(): array
    {
        return [
            'student' => 'required|integer|exists:students,id',
            'giver'   => 'required|integer|exists:users,id',
            'course'  => 'required|integer|exists:courses,id',
            'date'    => 'required|date|date_format:Y-m-d|after:today',
            'type' => 'required|string|in:أوقاف,داخلي',
            'parts'   => 'required|array|min:1',
            'parts.*' => 'required|integer|between:1,30',
        ];
    }

    /**
     * 2. الشرط المخصص: التحقق من أن الطالب موجود ومسجل في هذا الكورس
     */
   public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $studentId = $this->input('student');
            $courseId = $this->input('course');

            if ($studentId && $courseId) {

                $isEnrolled = DB::table('student_circles')
                    ->join('circles', 'student_circles.circle', '=', 'circles.id')
                    ->where('student_circles.student', $studentId)
                    ->where('circles.course_id', $courseId)
                    ->exists();

                if (!$isEnrolled) {
                    $validator->errors()->add('student', 'The selected student is not registered in any circle belonging to this course.');
                }
            }
        });
    }

    /**
     * رسائل الخطأ المخصصة
     */
    public function messages(): array
    {
        return [
            'student.required' => 'The student field is required.',
            'student.integer'  => 'The student field must be an integer.',
            'student.exists'   => 'The selected student does not exist.',

            'giver.required' => 'The giver field is required.',
            'giver.integer'  => 'The giver field must be an integer.',
            'giver.exists'   => 'The selected giver does not exist.',

            'course.required' => 'The course field is required.',
            'course.integer'  => 'The course field must be an integer.',
            'course.exists'   => 'The selected course does not exist.',

            'date.required'   => 'The date field is required.',
            'date.date'       => 'The date field must be a valid date.',
            'date.format'     => 'The date must be in the format YYYY-MM-DD.',
            'date.after' => "The date must be a future date (greater than today).",
            'type.required'   => 'The type field is required.',
            'type.string'     => 'The type field must be a string.',
            'type.max'        => 'The type may not be greater than 255 characters.',
            'type.in'         => 'The type must be either "أوقاف" or "داخلي".',
            'parts.required'  => 'The parts field is required.',
            'parts.array'     => 'The parts field must be an array.',
            'parts.min'       => 'The parts field must have at least one part.',
            'parts.*.required' => 'Each part is required.',
            'parts.*.integer'  => 'Each part must be an integer.',
            'parts.*.between'  => 'Each part must be between 1 and 30.',
        ];
    }


    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'errors'  => $validator->errors()
        ], 422));
    }
}
