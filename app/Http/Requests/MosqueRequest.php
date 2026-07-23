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
            'name.required' => 'اسم المسجد مطلوب ولا يمكن أن يكون فارغاً.',
            'name.string'   => 'اسم المسجد يجب أن يكون نصاً صالحاً.',
            'name.max'      => 'اسم المسجد طويل جداً؛ الحد الأقصى 255 حرفاً.',
            'name.unique'   => 'اسم المسجد مسجّل مسبقاً في النظام.',
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
