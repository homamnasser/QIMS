<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
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
                    'permissions' => array_map('intval', $permissions)
                ]);
            }
        }
    }

    public function rules(): array
    {
        $roleId = $this->route('id');

        return [
            'name' => [
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($roleId),
            ],
            'permissions' => 'array|min:1',
            'permissions.*' => 'integer|exists:permissions,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'           => 'This role name already exists, please choose another.',
            'name.max'              => 'The name may not be greater than 255 characters.',
            'permissions.*.integer' => 'Each permission ID must be a number.',
            'permissions.*.exists'  => 'One or more selected permissions are invalid.',

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
