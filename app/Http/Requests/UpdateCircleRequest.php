<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCircleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // بما أنك لم تضع قيوداً بعد، نتركها true
        return true;
    }

    /**
     * القواعد الخاصة بعملية التحديث.
     * استخدمنا 'sometimes' لتسمح بتحديث حقول معينة دون غيرها.
     */
    public function rules(): array
    {
        return [
            'name'       => 'sometimes|unique:circles,name,' . $this->route('id') . '|string|max:255',
            'teacher_id' => 'sometimes|exists:users,id',
            'course_id'  => 'sometimes|exists:courses,id',
        ];
    }
    public function messages(): array
    {
        return [
            'name.string' => 'The name must be a string.',
            'name.max'    => 'The name may not be greater than 255 characters.',
            'teacher_id.exists' => 'The selected teacher does not exist.',
            'course_id.exists'  => 'The selected course does not exist.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message'  => $validator->errors()
        ], 422));
    }
}
