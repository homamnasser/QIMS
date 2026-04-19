<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateLessonRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'start_page' => 'sometimes|integer|min:1',
            'end_page' => 'sometimes|integer|gte:start_page',
            'subject_id' => 'sometimes|exists:subjects,id',
        ];
    }
    public function messages(): array
    {
        return [
            'name.string' => 'The lesson name must be a string.',
            'name.max' => 'The lesson name may not be greater than 255 characters.',

            'description.string' => 'The description must be a string.',

            'start_page.integer' => 'The start page must be an integer.',
            'start_page.min' => 'The start page must be at least 1.',

            'end_page.integer' => 'The end page must be an integer.',
            'end_page.gte' => 'The end page must be greater than or equal to the start page.',

            'subject_id.exists' => 'The selected subject does not exist.',

        ];
    }
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message' =>  $validator->errors(),
        ], 422));
    }
}
