<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class StoreSabrRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['giver' => auth()->id()]);
    }

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
                    $validator->errors()->add('student', 'الطالب المحدد غير مسجّل في أي حلقة تابعة لهذا الكورس.');
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
            'student.required' => 'حقل الطالب مطلوب.',
            'student.integer'  => 'حقل الطالب يجب أن يكون عدداً صحيحاً.',
            'student.exists'   => 'الطالب المحدد غير موجود.',

            'giver.required' => 'حقل المُسبِّر مطلوب.',
            'giver.integer'  => 'حقل المُسبِّر يجب أن يكون عدداً صحيحاً.',
            'giver.exists'   => 'المُسبِّر المحدد غير موجود.',

            'course.required' => 'حقل الكورس مطلوب.',
            'course.integer'  => 'حقل الكورس يجب أن يكون عدداً صحيحاً.',
            'course.exists'   => 'الكورس المحدد غير موجود.',

            'date.required'   => 'حقل التاريخ مطلوب.',
            'date.date'       => 'حقل التاريخ يجب أن يكون تاريخاً صالحاً.',
            'date.format'     => 'التاريخ يجب أن يكون بصيغة YYYY-MM-DD.',
            'date.after' => "التاريخ يجب أن يكون تاريخاً مستقبلياً (بعد اليوم).",
            'type.required'   => 'حقل النوع مطلوب.',
            'type.string'     => 'حقل النوع يجب أن يكون نصاً.',
            'type.max'        => 'حقل النوع يجب ألا يتجاوز 255 حرفاً.',
            'type.in'         => 'النوع يجب أن يكون إما "أوقاف" أو "داخلي".',
            'parts.required'  => 'حقل الأجزاء مطلوب.',
            'parts.array'     => 'حقل الأجزاء يجب أن يكون مصفوفة.',
            'parts.min'       => 'حقل الأجزاء يجب أن يحتوي على جزء واحد على الأقل.',
            'parts.*.required' => 'كل جزء مطلوب.',
            'parts.*.integer'  => 'كل جزء يجب أن يكون عدداً صحيحاً.',
            'parts.*.between'  => 'كل جزء يجب أن يكون بين 1 و 30.',
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
