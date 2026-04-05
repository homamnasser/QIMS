<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'role_id'    => 'required|exists:roles,id'
        ];
    }


    public function messages(): array
    {
        return [
            'phone.unique'   => 'the phone already exist',
            'phone.phone'    => 'please enter a valid phone number',
            'email.email'    => 'Please enter a valid email address in the format name@gmail.com',
            'password.min'   => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'role_id.required' => 'The role field is required.',
            'role_id.exists'   => 'The selected role is invalid.',

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
