<?php

namespace App\Http\Requests;

use App\Models\CourseDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCourseCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


       public function rules(): array
    {
        $courseDate = $this->route('courseDate');
        $courseDateId = ($courseDate instanceof CourseDate) ? $courseDate->id : $courseDate;

        $courseId = CourseDate::where('id', $courseDateId)->value('course_id');

        return [
            'lesson_ids'   => 'required|array|min:1',
            'lesson_ids.*' => [
                'integer',
                'distinct',
                'exists:lessons,id',
                function ($attribute, $value, $fail) use ($courseId, $courseDateId) {
                    $exists = DB::table('course_date_lesson')
                        ->join('course_dates', 'course_date_lesson.course_date_id', '=', 'course_dates.id')
                        ->where('course_dates.course_id', $courseId)
                        ->where('course_date_lesson.lesson_id', $value)
                        ->where('course_date_lesson.course_date_id', '!=', $courseDateId)
                        ->exists();

                    if ($exists) {
                        $fail("The lesson (ID: {$value}) is already assigned to another date in this course.");
                    }
                },
            ],
        ];
    }


    public function messages(): array
    {
        return [
            'lesson_ids.required' => 'At least one lesson must be assigned to the course date.',
            'lesson_ids.array'    => 'The lesson_ids field must be an array of lesson IDs.',
            'lesson_ids.min'      => 'At least one lesson must be assigned to the course date.',
            'lesson_ids.*.integer' => 'Each lesson ID must be an integer.',
            'lesson_ids.*.distinct' => 'Duplicate lesson IDs are not allowed.',
            'lesson_ids.*.exists' => 'One or more of the provided lesson IDs do not exist in the system.',
        ];
    }


    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'status'  => 'error',
            'errors'  => $validator->errors()
        ], 422));
    }

}
