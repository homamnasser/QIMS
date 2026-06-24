<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name'          => 'required|string|max:50',
            'last_name'           => 'required|string|max:50',
            'phone_number'        => [
                'nullable',
                'string',
                'regex:/^09[0-9]{8}$/',
                'unique:students,phone_number'
            ],
            'birth_date'          => 'required|date',
            'academic_class'      => 'required|string',
            'reading_level'       => 'required|in:level_1,level_2,level_3',
            'father_name'         => 'required|string',
            'parent_social_state' => 'required|in:married,divorced,widowed',
            'father_phone'        => [
                'required',
                'string',
                'regex:/^09[0-9]{8}$/'
            ],
            'password'            => 'required|min:8|confirmed',
            'username'            => 'nullable|string|unique:students,username',
            'image'               => 'sometimes|nullable|file|image|mimes:jpg,jpeg,png|max:5120',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->first_name && $this->last_name) {
            $this->merge([
                'username' => strtolower($this->first_name . '-' . $this->last_name),
            ]);
        }
    }
    public function messages()
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'phone_number.regex' => 'Phone number must start with 09 and be followed by 8 digits.',
            'phone_number.unique' => 'The phone number has already been taken.',
            'birth_date.required' => 'Birth date is required.',
            'academic_class.required' => 'Academic class is required.',
            'reading_level.required' => 'Reading level is required.',
            'father_name.required' => 'Father name is required.',
            'parent_social_state.required' => 'Parent social state is required.',
            'father_phone.required' => 'Father phone is required.',
            'father_phone.regex' => 'Father phone number must start with 09 and be followed by 8 digits.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message'  => $validator->errors()
        ], 422));
    }
}
