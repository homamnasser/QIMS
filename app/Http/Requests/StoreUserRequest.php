<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Enums\RoleFamily;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:55',
            'last_name'  => 'required|string|max:55',
            'phone'      => ['required', 'unique:users,phone'],
            'password'   => 'required|string|min:8|confirmed',
            'email'      => 'required|email|unique:users,email',
            'birth_date' => 'required|date',
            'role_id'    => [
                'required',
                Rule::exists('roles', 'id')->where(
                    fn ($query) => $query->whereNotIn('role_family', [
                        RoleFamily::Student->value,
                        RoleFamily::SuperAdmin->value,
                    ])
                ),
            ],
            'image'      => 'sometimes|nullable|file|image|mimes:jpg,jpeg,png|max:5120',
        ];
    }


    public function messages(): array
    {
        return [
            'phone.unique'   => 'رقم الهاتف مستخدم مسبقاً.',
            'phone.phone'    => 'يرجى إدخال رقم هاتف صالح.',
            'email.email'    => 'يرجى إدخال بريد إلكتروني صالح.',
            'password.min'   => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'role_id.required' => 'حقل الدور مطلوب.',
            'role_id.exists'   => 'الدور المحدد غير صالح.',

        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message'  => $validator->errors()
        ], 422));
    }
}
