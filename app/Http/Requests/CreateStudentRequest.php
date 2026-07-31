<?php

namespace App\Http\Requests;

use App\Enums\RoleFamily;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'phone_number' => [
                'required',
                'string',
                'regex:/^09[0-9]{8}$/',
                'unique:students,phone_number',
            ],
            'birth_date' => 'required|date',
            'academic_class' => 'required|string',
            'reading_level' => 'required|in:level_1,level_2,level_3',
            'father_name' => 'required|string',
            'parent_social_state' => 'required|in:married,divorced,widowed',
            'father_phone' => [
                'required',
                'string',
                'regex:/^09[0-9]{8}$/',
            ],
            'password' => 'required|min:8|confirmed',
            'username' => 'nullable|string|unique:students,username',
            'mosque_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('mosques', 'id'),
            ],
            'image' => 'sometimes|nullable|file|image|mimes:jpg,jpeg,png,webp|max:8192',
            'role_id' => [
                'nullable',
                Rule::exists('roles', 'id')->where(
                    fn ($query) => $query->where('role_family', RoleFamily::Student->value)
                ),
            ],
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->first_name && $this->last_name) {
            $this->merge([
                'username' => strtolower($this->first_name.'-'.$this->last_name),
            ]);
        }
    }

    public function messages()
    {
        return [
            'first_name.required' => 'الاسم الأول مطلوب.',
            'last_name.required' => 'اسم العائلة مطلوب.',
            'phone_number.required' => 'رقم هاتف الطالب مطلوب.',
            'phone_number.regex' => 'رقم هاتف الطالب يجب أن يبدأ بـ 09 ويتكون من 10 أرقام.',
            'phone_number.unique' => 'رقم هاتف الطالب مستخدم مسبقاً.',
            'birth_date.required' => 'تاريخ الميلاد مطلوب.',
            'academic_class.required' => 'الصف الدراسي مطلوب.',
            'reading_level.required' => 'مستوى القراءة مطلوب.',
            'father_name.required' => 'اسم الأب مطلوب.',
            'parent_social_state.required' => 'الحالة الاجتماعية لولي الأمر مطلوبة.',
            'father_phone.required' => 'رقم هاتف الأب مطلوب.',
            'father_phone.regex' => 'رقم هاتف الأب أو ولي الأمر يجب أن يبدأ بـ 09 ويتكون من 10 أرقام.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'mosque_id.exists' => 'المسجد المحدد غير موجود.',
            'image.image' => 'يجب أن يكون الملف صورة صالحة.',
            'image.mimes' => 'يجب أن تكون الصورة بصيغة JPG أو PNG أو WebP.',
            'image.max' => 'حجم الصورة يجب ألا يتجاوز 8 ميغابايت.',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'code' => 422,
            'message' => $validator->errors(),
        ], 422));
    }
}
