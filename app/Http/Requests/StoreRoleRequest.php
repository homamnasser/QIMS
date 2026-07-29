<?php

namespace App\Http\Requests;

use App\Enums\RoleFamily;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /** تحضير البيانات قبل التحقق */
    protected function prepareForValidation(): void
    {
        if ($this->has('permissions')) {
            $permissions = $this->permissions;

            if (is_string($permissions)) {
                $permissions = explode(',', $permissions);
            }

            if (is_array($permissions)) {
                $this->merge([
                    'permissions' => array_map('intval', $permissions),
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $requiresPrivilegedConfirmation = in_array($this->input('role_family'), [
            RoleFamily::Admin->value,
            RoleFamily::Supervisor->value,
            RoleFamily::FieldSupervisor->value,
        ], true);

        return [
            'name' => 'required|string|unique:roles,name|max:255',
            'role_family' => ['required', Rule::in(RoleFamily::assignableValues())],
            'confirm_privileged_family' => $requiresPrivilegedConfirmation
                ? ['required', 'accepted']
                : ['sometimes', 'boolean'],
            'permissions' => 'required|min:1',
            'permissions.*' => 'integer|exists:permissions,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الدور مطلوب.',
            'name.string' => 'اسم الدور يجب أن يكون نصاً صالحاً.',
            'name.unique' => 'اسم الدور مستخدم مسبقاً.',
            'name.max' => 'الاسم يجب ألا يتجاوز 255 حرفاً.',
            'role_family.required' => 'عائلة الدور مطلوبة.',
            'role_family.in' => 'عائلة الدور المحددة غير صالحة.',
            'confirm_privileged_family.required' => 'يجب تأكيد منح الأهلية الإدارية أو الإشرافية لهذا الدور.',
            'confirm_privileged_family.accepted' => 'يجب تأكيد منح الأهلية الإدارية أو الإشرافية لهذا الدور.',
            'permissions.required' => 'يجب إسناد صلاحية واحدة على الأقل.',
            'permissions.min' => 'يرجى اختيار صلاحية واحدة على الأقل.',
            'permissions.*.integer' => 'معرّف كل صلاحية يجب أن يكون رقماً.',
            'permissions.*.exists' => 'صلاحية واحدة أو أكثر من المحددة غير موجودة.',
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
