<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSabrResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'value' => 'required|string|max:100',
            'note'  => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => 'The value field is required.',
            'value.string'   => 'The value field must be a string.',
            'value.max'      => 'The value may not be greater than 100 characters.',
            'note.required'  => 'The note field is required.',
            'note.string'    => 'The note field must be a string.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'errors'  => $validator->errors()
        ], 422));
    }
}
