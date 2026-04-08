<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class MosqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:mosques,name,'. $this->route('id'),
        ];
    }


    public function messages(): array
    {
        return [
            'name.required' => 'The mosque name is required and cannot be empty.',
            'name.string'   => 'The mosque name must be a valid text string.',
            'name.max'      => 'The mosque name is too long; the maximum limit is 255 characters.',
            'name.unique'   => 'This mosque name has already been registered in our system.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message'  => $validator->errors()
        ], 422));
    }
}
