<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class CreateNoteRequest extends FormRequest
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
            'student_id'  => 'required|integer|exists:students,id',
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
        ];
    }
    public function messages(): array
    {
        return [
            'student_id.required' => "Student ID is required.",
            'student_id.integer'  => "Student ID must be an integer.",
            'student_id.exists'   => "Selected student does not exist.",
            'title.required'      => "Note title is required.",
            'title.string'        => "Note title must be a string.",
            'title.max'           => "Note title cannot exceed 255 characters.",
            'description.required' => "Note description is required.",
            'description.string'   => "Note description must be a string.",
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
