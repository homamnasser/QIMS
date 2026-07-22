<?php

namespace App\Http\Requests;

use App\Enums\RoleFamily;
use App\Models\Role;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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

    public function rules(): array
    {
        $roleId = $this->route('id');
        $storedRole = Role::find($roleId);
        $storedFamily = $storedRole?->role_family;
        $storedFamilyValue = $storedFamily instanceof RoleFamily
            ? $storedFamily->value
            : $storedFamily;
        $requestedFamily = $this->input('role_family');
        $requiresPrivilegedConfirmation = $requestedFamily !== null
            && $requestedFamily !== $storedFamilyValue
            && in_array($requestedFamily, [
                RoleFamily::Admin->value,
                RoleFamily::Supervisor->value,
            ], true);

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($roleId),
            ],
            'role_family' => ['sometimes', Rule::in(RoleFamily::assignableValues())],
            'confirm_privileged_family' => $requiresPrivilegedConfirmation
                ? ['required', 'accepted']
                : ['sometimes', 'boolean'],
            'permissions' => 'array|min:1',
            'permissions.*' => 'integer|exists:permissions,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'This role name already exists, please choose another.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'role_family.in' => 'The selected role family is invalid.',
            'confirm_privileged_family.required' => 'يجب تأكيد منح الأهلية الإدارية أو الإشرافية لهذا الدور.',
            'confirm_privileged_family.accepted' => 'يجب تأكيد منح الأهلية الإدارية أو الإشرافية لهذا الدور.',
            'permissions.*.integer' => 'Each permission ID must be a number.',
            'permissions.*.exists' => 'One or more selected permissions are invalid.',

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
