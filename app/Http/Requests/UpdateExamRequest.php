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
        ];
    }

    public function messages(): array
    {
        return [
            'mark.required' => 'The exam mark field is required to update the score.',
            'mark.numeric'  => 'The exam mark must be a valid integer or floating-point number.',
            'mark.min'      => 'The exam mark cannot be less than zero.',
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
                        $validator->errors()->add('mark', "The updated exam mark cannot be greater than the subject max mark limit ({$subject->max_marks}).");
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
