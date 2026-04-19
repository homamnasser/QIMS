<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreLessonRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'start_page' => 'required|integer|min:1',
            'end_page' => 'required|integer|gte:start_page',
            'subject_id' => 'required|exists:subjects,id',
        ];
    }
    public function messages(): array
    {
        return [
            'name.required' => 'The lesson name is required.',
            'name.string' => 'The lesson name must be a string.',
            'name.max' => 'The lesson name may not be greater than 255 characters.',

            'description.required' => 'The lesson description is required.',
            'description.string' => 'The description must be a string.',

            'start_page.required' => 'The start page is required.',
            'start_page.integer' => 'The start page must be an integer.',
            'start_page.min' => 'The start page must be at least 1.',

            'end_page.required' => 'The end page is required.',
            'end_page.integer' => 'The end page must be an integer.',
            'end_page.gte' => 'The end page must be greater than or equal to the start page.',

            'subject_id.required' => 'A subject must be selected for the lesson.',
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
