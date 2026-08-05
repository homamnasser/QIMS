<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class UpdateExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // مطلوب فقط حقل العلامة للتعديل 🎯
            'mark' => 'required|numeric|min:0',
            'occurred_at' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'mark.required' => 'حقل علامة الامتحان مطلوب لتحديث الدرجة.',
            'mark.numeric'  => 'علامة الامتحان يجب أن تكون عدداً صحيحاً أو عشرياً صالحاً.',
            'occurred_at.date' => 'تاريخ الاختبار ليس بصيغة تاريخ صالحة.',
            'mark.min'      => 'علامة الامتحان لا يمكن أن تكون أقل من صفر.',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $mark = $this->input('mark');

            $examId = $this->route('id');

            if ($mark === null || !$examId) {
                return;
            }

            $exam = DB::table('exams')->where('id', $examId)->first();

            if ($exam) {
                $subject = DB::table('subjects')->where('id', $exam->subject)->first();

                if ($subject && isset($subject->max_marks)) {
                    if ((float)$mark > (float)$subject->max_marks) {
                        $validator->errors()->add('mark', "علامة الامتحان المحدّثة لا يمكن أن تتجاوز الحد الأقصى لعلامة المادة ({$subject->max_marks}).");
                    }
                }
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
