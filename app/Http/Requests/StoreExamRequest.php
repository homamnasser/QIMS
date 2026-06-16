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
        ];
    }

    /**
     * 💬 Custom English validation messages for basic rules
     */
    public function messages(): array
    {
        return [
            'student.required' => 'The student field is required.',
            'student.exists'   => 'The selected student record does not exist in our system.',

            'subject.required' => 'The subject field is required.',
            'subject.exists'   => 'The selected subject record does not exist in our system.',

            'course.required'  => 'The course field is required.',
            'course.exists'    => 'The selected course record does not exist in our system.',

            'mark.required'    => 'The exam mark field is required to record the score.',
            'mark.numeric'     => 'The exam mark must be a valid integer or floating-point number.',
            'mark.min'         => 'The exam mark cannot be less than zero.',
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
                $validator->errors()->add('subject', 'The selected subject does not belong to this specific course.');
            }

            // 2️⃣ Second Condition: Check if the student belongs to a circle inside this course 🎯
            $studentInCourse = DB::table('student_circles')
                ->join('circles', 'student_circles.circle', '=', 'circles.id')
                ->where('student_circles.student', $studentId)
                ->where('circles.course_id', $courseId) // 👈 تم التعديل إلى course_id ليطابق جدولك تماماً
                ->exists();

            if (!$studentInCourse) {
                $validator->errors()->add('student', 'The selected student is not registered in any circle belonging to this course.');
            }

            // 3️⃣ Third Condition: Verify that the entered mark does not exceed the subject max_mark
            $subject = DB::table('subjects')->where('id', $subjectId)->first();

            if ($subject && isset($subject->max_marks)) {
                if ($mark > $subject->max_marks) {
                    $validator->errors()->add('mark', "The exam mark cannot be greater than the subject max mark limit ({$subject->max_marks}).");
                }
            }

            // 4️⃣ Fourth Condition: Check if the student already has a recorded mark for this subject in this course
            $examExists = DB::table('exams')
                ->where('student', $studentId)
                ->where('subject', $subjectId)
                ->where('course', $courseId)
                ->exists();

            if ($examExists) {
                $validator->errors()->add('student', 'The student already has a recorded mark for this subject in this course.');
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
