<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreProjectRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'name'        => 'required|string|unique:projects,name|max:255',
            'description' => 'required|string',
            'audience'    => 'required|string',

            'supervisor'  => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);

                    if ($user && !$user->hasRole('admin')) {
                        $fail('The selected user must have an Admin role to be a project supervisor.');
                    }
                },
            ],

            'logo'        => 'mimes:jpg,jpeg,png,pdf|max:5120',
        ];
    }


    public function messages(): array
    {
        return [
            'name.required'     => 'The project name is required.',
            'name.unique'       => 'This project name is already taken, please choose another.',
            'name.max'          => 'The project name may not be greater than 255 characters.',

            'description.required' => 'Please provide a project description.',
            'audience.required'    => 'Target audience is required.',

            'supervisor.required' => 'A project supervisor must be assigned.',
            'supervisor.exists'   => 'The selected supervisor does not exist in our records.',

            'logo.mimes'         => 'The logo must be a file of type: jpg, png, jpeg.',
            'logo.max'           => 'The logo size may not exceed 5MB.',

        ];
    }
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message' =>  $validator->errors(),
        ], 422));
    }
}
