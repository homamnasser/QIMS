<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MobileLoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('login')) {
            return;
        }

        foreach (['email', 'username'] as $alias) {
            if ($this->filled($alias)) {
                $this->merge(['login' => $this->input($alias)]);

                return;
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'اسم المستخدم أو البريد الإلكتروني مطلوب.',
            'password.required' => 'حقل كلمة المرور مطلوب.',
            'device_name.required' => 'اسم الجهاز مطلوب لإدارة جلسة الموبايل.',
        ];
    }
}
