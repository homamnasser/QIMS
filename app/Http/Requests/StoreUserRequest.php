<?php

namespace App\Http\Requests;

use App\Enums\RoleFamily;
use App\Enums\StaffWorkScope;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $staff = $this->user();

        if ($staff instanceof User && $staff->isMosqueScoped()) {
            $this->merge([
                'work_scope' => StaffWorkScope::Mosque->value,
                'mosque_id' => $staff->mosque_id,
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:55',
            'last_name' => 'required|string|max:55',
            'phone' => ['required', 'unique:users,phone'],
            'password' => 'required|string|min:8|confirmed',
            'email' => 'required|email|unique:users,email',
            'birth_date' => 'required|date',
            'work_scope' => [
                'required',
                Rule::enum(StaffWorkScope::class),
            ],
            'mosque_id' => [
                Rule::requiredIf(
                    fn (): bool => $this->input('work_scope') === StaffWorkScope::Mosque->value
                ),
                Rule::prohibitedIf(
                    fn (): bool => $this->input('work_scope') === StaffWorkScope::Institute->value
                ),
                'nullable',
                'integer',
                Rule::exists('mosques', 'id'),
            ],
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where(
                    fn ($query) => $query->whereNotIn('role_family', [
                        RoleFamily::Student->value,
                        RoleFamily::SuperAdmin->value,
                    ])
                ),
            ],
            'image' => 'sometimes|nullable|file|image|mimes:jpg,jpeg,png|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'رقم الهاتف مستخدم مسبقاً.',
            'phone.phone' => 'يرجى إدخال رقم هاتف صالح.',
            'email.email' => 'يرجى إدخال بريد إلكتروني صالح.',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'role_id.required' => 'حقل الدور مطلوب.',
            'role_id.exists' => 'الدور المحدد غير صالح.',
            'work_scope.required' => 'يجب تحديد نطاق عمل الموظف.',
            'work_scope.enum' => 'نطاق العمل المحدد غير صالح.',
            'mosque_id.required' => 'يجب اختيار مسجد واحد للموظف.',
            'mosque_id.prohibited' => 'لا يمكن ربط الموظف على مستوى المعهد بمسجد محدد.',
            'mosque_id.exists' => 'المسجد المحدد غير موجود.',

        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'code' => 422,
            'message' => $validator->errors(),
        ], 422));
    }
}
