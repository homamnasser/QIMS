<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
                'permissions' => array_map('intval', $permissions)
            ]);
        }
    }
}

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:roles,name|max:255',
            'permissions' => 'required|min:1',
            'permissions.*' => 'integer|exists:permissions,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'The role name is required.',
            'name.string'          => 'The role name must be a valid text.',
            'name.unique'          => 'This role name is already taken.',
            'name.max'             => 'The name cannot exceed 255 characters.',
            'permissions.required' => 'At least one permission must be assigned.',
            'permissions.min'      => 'Please select at least one permission.',
            'permissions.*.integer' => 'Each permission ID must be a number.',
            'permissions.*.exists'  => 'One or more of the selected permissions do not exist.',
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
