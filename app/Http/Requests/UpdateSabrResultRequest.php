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
            'value.required' => 'حقل النتيجة مطلوب.',
            'value.string'   => 'حقل النتيجة يجب أن يكون نصاً.',
            'value.max'      => 'حقل النتيجة يجب ألا يتجاوز 100 حرف.',
            'note.required'  => 'حقل الملاحظة مطلوب.',
            'note.string'    => 'حقل الملاحظة يجب أن يكون نصاً.',
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
