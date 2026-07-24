<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IdentifySurveyStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selfnumber' => ['required', 'string', 'max:40', 'regex:/^[A-Z][A-Z0-9]{0,11}-[0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'selfnumber.required' => 'أدخل الرقم الذاتي للطالب.',
            'selfnumber.regex' => 'صيغة الرقم الذاتي غير صحيحة.',
        ];
    }
}
