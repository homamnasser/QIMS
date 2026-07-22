<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Enums\RoleFamily;

class StoreStudentMarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 📋 التحقق من الحقول ديناميكياً (المشرف يدخل كل شيء، والأستاذ يدخل حقوله فقط)
     */
    public function rules(): array
    {
        $rules = [
            'student'                   => 'required|integer|exists:students,id',
            'course'                    => 'required|integer|exists:courses,id',
            'circle'                    => 'required|integer|exists:circles,id',
            'attendance_marks'          => 'required|numeric|min:0',
            'memorization_marks'        => 'required|numeric|min:0',
            'reading_improvement_marks' => 'required|numeric|min:0',
            'exams_marks'               => 'required|numeric|min:0',
            'behavior_teacher_marks'    => 'required|numeric|min:0',
        ];

        $user = Auth::user();

        // 🎯 إذا كان المستخدم مشرفاً أو أدمن، نلزمه بإدخال حقوله الإدارية أيضاً ليصبح المجموع كاملاً
        if ($user && !$user->hasRoleFamily(RoleFamily::Teacher)) {
            $rules['behavior_admin_marks'] = 'required|numeric|min:0';
            $rules['sabr_bonus']           = 'required|numeric|min:0';
            $rules['attendance_marks']     = 'required|numeric|min:0';
        }

        return $rules;
    }

    /**
     * 🔐 شروط منطق العمل (يتم تجاوز شرط الأستاذ إذا كان المستخدم مشرفاً)
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $studentId = $this->input('student');
            $courseId  = $this->input('course');
            $circleId  = $this->input('circle');
            $currentUserId = Auth::id();

            if (!$studentId || !$courseId || !$circleId) {
                return;
            }

            // 1️⃣ الشرط الأول: التأكد أن الحلقة تابعة للكورس المدخل
            $circleInCourse = DB::table('circles')
                ->where('id', $circleId)
                ->where('course_id', $courseId)
                ->exists();

            if (!$circleInCourse) {
                $validator->errors()->add('circle', 'The selected circle does not belong to this specific course.');
            }

            // 2️⃣ الشرط الثاني: التأكد أن الطالب مسجل فعلياً في هذه الحلقة
            $studentInCircle = DB::table('student_circles')
                ->where('student', $studentId)
                ->where('circle', $circleId)
                ->exists();

            if (!$studentInCircle) {
                $validator->errors()->add('student', 'The selected student is not registered in this specific circle.');
            }

            // 3️⃣ الشرط الثالث: إذا كان المسجل "أستاذ"، يجب أن يكون مسؤولاً عن الحلقة (المشرف يتجاوز هذا الفحص تلقائياً 🛡️)
            if (Auth::user()->hasRoleFamily(RoleFamily::Teacher)) {
                $isActualTeacher = DB::table('circles')
                    ->where('id', $circleId)
                    ->where('teacher_id', $currentUserId)
                    ->exists();

                if (!$isActualTeacher) {
                    $validator->errors()->add('circle', 'Unauthorized! You are not the assigned teacher for this circle.');
                }
            }

            // 4️⃣ الشرط الرابع: منع التكرار
            $marksRecordExists = DB::table('student_marks')
                ->where('student', $studentId)
                ->where('course', $courseId)
                ->where('circle', $circleId)
                ->exists();

            if ($marksRecordExists) {
                $validator->errors()->add('student', 'A marks summary record already exists for this student in this circle and course.');
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
