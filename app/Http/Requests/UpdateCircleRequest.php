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
        return true;
    }


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
            'name.string' => 'الاسم يجب أن يكون نصاً.',
            'name.max'    => 'الاسم يجب ألا يتجاوز 255 حرفاً.',
            'teacher_id.exists' => 'المعلم المحدد غير موجود.',
            'course_id.exists'  => 'الكورس المحدد غير موجود.',
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
