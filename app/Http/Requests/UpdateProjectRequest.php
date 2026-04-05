<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProjectRequest extends FormRequest
{
    /**
     * تأكد من تغييرها إلى true للسماح بالطلب
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق لعملية التحديث
     */
    public function rules(): array
    {
        return [
            // استخدام ID المشروع في قاعدة unique لتجنب الخطأ إذا لم يتغير الاسم
            'name' => [
                'sometimes',
                'string',
                'max:255',
                'unique:projects,name,' . $this->route('id')
            ],

            'description' => 'sometimes|string',
            'audience'    => 'sometimes|string',

            'supervisor'  => [
                'sometimes',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if ($user && !$user->hasRole('admin')) {
                        $fail('The selected user must have an Admin role to be a project supervisor.');
                    }
                },
            ],

            'logo' => 'sometimes|nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ];
    }

    /**
     * تخصيص رسائل الخطأ
     */
    public function messages(): array
{
    return [
        'name.unique'         => 'This project name is already taken, please choose another.',
        'name.max'            => 'The project name may not be greater than 255 characters.',
        'name.string'         => 'The project name must be a valid text string.',

        'description.string'  => 'The description must be a valid text string.',
        'audience.string'     => 'The audience field must be a valid text string.',

        'supervisor.exists'   => 'The selected supervisor does not exist in our records.',

        'logo.mimes'          => 'The logo must be a file of type: jpg, jpeg, png, pdf.',
        'logo.max'            => 'The logo size may not be greater than 5MB.',
        'logo.file'           => 'The logo must be a valid uploaded file.',
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
