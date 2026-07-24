<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class MosqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('mosques', 'name')->ignore($this->route('id')),
            ],
            'mosque_code' => [
                'required',
                'string',
                'max:12',
                'regex:/^[A-Z][A-Z0-9]{0,11}$/',
                Rule::unique('mosques', 'mosque_code')->ignore($this->route('id')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mosque_code')) {
            $this->merge(['mosque_code' => strtoupper(trim((string) $this->mosque_code))]);
        }
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المسجد مطلوب ولا يمكن أن يكون فارغاً.',
            'name.string' => 'اسم المسجد يجب أن يكون نصاً صالحاً.',
            'name.max' => 'اسم المسجد طويل جداً؛ الحد الأقصى 255 حرفاً.',
            'name.unique' => 'اسم المسجد مسجّل مسبقاً في النظام.',
            'mosque_code.required' => 'رمز المسجد مطلوب.',
            'mosque_code.regex' => 'رمز المسجد يجب أن يبدأ بحرف لاتيني كبير ويحتوي على أحرف وأرقام فقط.',
            'mosque_code.unique' => 'رمز المسجد مستخدم لمسجد آخر.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 422,
            'message' => $validator->errors(),
        ], 422));
    }
}
