<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 📋 Basic input validation rules
     */
    public function rules(): array
    {
        return [
            'student' => 'required|integer|exists:students,id',
            'subject' => 'required|integer|exists:subjects,id',
            'course'  => 'required|integer|exists:courses,id',
            'mark'    => 'required|numeric|min:0',
            // تاريخ الاختبار كما وقع؛ نافذة التقييم تُقارَن به لا بلحظة الإدخال،
            // فيصبح الرصد المتأخر ممكناً بلا أن تسقط العلامة من الحساب.
            'occurred_at' => 'nullable|date',
        ];
    }

    /**
     * 💬 Custom English validation messages for basic rules
     */
    public function messages(): array
    {
        return [
            'student.required' => 'حقل الطالب مطلوب.',
            'student.exists'   => 'سجل الطالب المحدد غير موجود في النظام.',

            'subject.required' => 'حقل المادة مطلوب.',
            'subject.exists'   => 'سجل المادة المحدد غير موجود في النظام.',

            'course.required'  => 'حقل الكورس مطلوب.',
            'course.exists'    => 'سجل الكورس المحدد غير موجود في النظام.',

            'mark.required'    => 'حقل علامة الامتحان مطلوب لتسجيل الدرجة.',
            'mark.numeric'     => 'علامة الامتحان يجب أن تكون عدداً صحيحاً أو عشرياً صالحاً.',
            'mark.min'         => 'علامة الامتحان لا يمكن أن تكون أقل من صفر.',
        ];
    }

    /**
     * 🔐 Advanced business logic validations (Course bindings, Max mark, & Unique Exam)
     */
    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $studentId = $this->input('student');
            $subjectId = $this->input('subject');
            $courseId  = $this->input('course');
            $mark      = $this->input('mark');

            if (!$studentId || !$subjectId || !$courseId || $mark === null) {
                return;
            }

            // 1️⃣ First Condition: Check if the subject actually belongs to the selected course
            $subjectInCourse = DB::table('subjects')
                ->where('id', $subjectId)
                ->where('course_id', $courseId) // تأكد من اسم حقل الكورس في جدول المواد
                ->exists();

            if (!$subjectInCourse) {
                $validator->errors()->add('subject', 'المادة المحددة لا تنتمي لهذا الكورس.');
            }

            // 2️⃣ Second Condition: Check if the student belongs to a circle inside this course 🎯
            $studentInCourse = DB::table('student_circles')
                ->join('circles', 'student_circles.circle', '=', 'circles.id')
                ->where('student_circles.student', $studentId)
                ->where('circles.course_id', $courseId) // 👈 تم التعديل إلى course_id ليطابق جدولك تماماً
                ->exists();

            if (!$studentInCourse) {
                $validator->errors()->add('student', 'الطالب المحدد غير مسجّل في أي حلقة تابعة لهذا الكورس.');
            }

            // 3️⃣ Third Condition: Verify that the entered mark does not exceed the subject max_mark
            $subject = DB::table('subjects')->where('id', $subjectId)->first();

            if ($subject && isset($subject->max_marks)) {
                if ($mark > $subject->max_marks) {
                    $validator->errors()->add('mark', "علامة الامتحان لا يمكن أن تتجاوز الحد الأقصى لعلامة المادة ({$subject->max_marks}).");
                }
            }

            // 4️⃣ Fourth Condition: Check if the student already has a recorded mark for this subject in this course
            $examExists = DB::table('exams')
                ->where('student', $studentId)
                ->where('subject', $subjectId)
                ->where('course', $courseId)
                ->exists();

            if ($examExists) {
                $validator->errors()->add('student', 'الطالب يملك علامة مسجّلة مسبقاً لهذه المادة في هذا الكورس.');
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
