<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateStudentRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'first_name'          => 'sometimes|required|string|max:50',
            'last_name'           => 'sometimes|required|string|max:50',
            'phone_number'        => [
                'nullable',
                'string',
                'regex:/^09[0-9]{8}$/',
                'unique:students,phone_number,' . $this->route('id') 
            ],
            'birth_date'          => 'sometimes|required|date',
            'academic_class'      => 'sometimes|required|string',
            'reading_level'       => 'sometimes|required|in:level_1,level_2,level_3',
            'father_name'         => 'sometimes|required|string',
            'parent_social_state' => 'sometimes|required|in:married,divorced,widowed',
            'father_phone'        => [
                'sometimes',
                'required',
                'string',
                'regex:/^09[0-9]{8}$/'
            ],
            'password'            => 'sometimes|required|min:8|confirmed',
            'username'            => 'nullable|string|unique:students,username,' . $this->route('id'), // استثناء اليوزر نيم للطالب الحالي
        ];
    }

    /**
     * إعداد البيانات قبل الـ Validation وتوليد الـ username الجديد إذا تعدلت الأسماء.
     */
    protected function prepareForValidation()
    {
        // إذا قام الفرونت إند بإرسال الاسم الأول والأخير معاً للتعديل، نقوم بتحديث اليوزر نيم بناءً عليهما
        if ($this->has('first_name') && $this->has('last_name')) {
            $this->merge([
                'username' => strtolower($this->first_name . '.' . $this->last_name),
            ]);
        }
        // أما لو أرسل فقط الاسم الأول، نأخذ الاسم الأخير القديم من الموديل المخزن في السيرفر (إذا كان ممرراً عبر Route Model Binding)
        // ولتجنب التعقيد، يفضل دائماً إخبار فريق الفرونت إند بإرسال الحقلين معاً في حال الرغبة بتغيير أحدهما ليتم توليد الـ username بشكل صحيح ومترابط.
    }

    public function messages()
    {
        return [
            'first_name.required'          => 'First name is required.',
            'last_name.required'           => 'Last name is required.',
            'phone_number.regex'           => 'Phone number must start with 09 and be followed by 8 digits.',
            'phone_number.unique'          => 'The phone number has already been taken.',
            'birth_date.required'          => 'Birth date is required.',
            'academic_class.required'      => 'Academic class is required.',
            'reading_level.required'       => 'Reading level is required.',
            'father_name.required'         => 'Father name is required.',
            'parent_social_state.required' => 'Parent social state is required.',
            'father_phone.required'        => 'Father phone is required.',
            'father_phone.regex'           => 'Father phone number must start with 09 and be followed by 8 digits.',
            'password.required'            => 'Password is required.',
            'password.min'                 => 'Password must be at least 8 characters.',
            'password.confirmed'           => 'Password confirmation does not match.',
            'username.unique'              => 'The username has already been taken.',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'message' => $validator->errors()
        ], 422));
    }
}
