<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class CreateCircleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'       => 'required|unique:circles|string|max:255',
            'teacher_id' => 'required|exists:users,id',
            'course_id'  => 'required|exists:courses,id',
        ];
    }
    public function messages(): array
    {
        return [
            'name.required'       => 'Circle name is required.',
            'name.string'         => 'Circle name must be a string.',
            'name.max'            => 'Circle name cannot exceed 255 characters.',
            'name.unique'         => 'A circle with this name already exists.',
            'teacher_id.required' => 'Teacher ID is required.',
            'teacher_id.exists'   => 'The specified teacher does not exist.',
            'course_id.required'  => 'Course ID is required.',
            'course_id.exists'    => 'The specified course does not exist.',
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
