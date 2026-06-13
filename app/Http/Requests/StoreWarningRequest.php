<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreWarningRequest extends FormRequest
{
    /**
     * تحديد صلاحية تمرير الطلب
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق لإنشاء إنذار 🎯
     */

    protected function prepareForValidation()
    {
        $this->getInputSource()->add([
            'warner' => auth()->id(),
        ]);

        $this->request->add([
            'warner' => auth()->id(),
        ]);
    }
    public function rules(): array
    {
        return [
            'student'  => 'required|integer|exists:students,id',
            'warner'      => 'required|integer|exists:users,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
        ];
    }

    /**
     * رسائل التحقق المخصصة بستايل مشروعك الموحد
     */
    public function messages(): array
    {
        return [
            'student.required'  => 'student_id is required.',
            'student.integer'   => 'student_id must be an integer.',
            'student.exists'    => 'The specified student does not exist.',
            'title.required'       => 'title is required.',
            'title.string'         => 'title must be a string.',
            'title.max'            => 'title may not be greater than 255 characters.',
            'description.string'   => 'description must be a string.',
        ];
    }

    /**
     * معالجة الفشل بـ API JSON Response متناسق
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'errors'  => $validator->errors()
        ], 422));
    }
}
