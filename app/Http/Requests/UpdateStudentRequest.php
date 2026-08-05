<?php

namespace App\Http\Requests;

use App\Enums\RoleFamily;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

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
            'first_name' => 'sometimes|required|string|max:50',
            'last_name' => 'sometimes|required|string|max:50',
            'phone_number' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^09[0-9]{8}$/',
                'unique:students,phone_number,'.$this->route('id'),
            ],
            'birth_date' => 'sometimes|required|date',
            'academic_class' => 'sometimes|required|string',
            'reading_level' => 'sometimes|required|in:level_1,level_2,level_3',
            'father_name' => 'sometimes|required|string',
            'parent_social_state' => 'sometimes|required|in:married,divorced,widowed',
            'father_phone' => [
                'sometimes',
                'required',
                'string',
                'regex:/^09[0-9]{8}$/',
            ],
            'password' => 'sometimes|required|min:8|confirmed',
            'username' => [
                'bail',
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/\A[a-z0-9]+(?:[._-][a-z0-9]+)*\z/',
                Rule::unique('students', 'username')->ignore((int) $this->route('id')),
            ],
            'mosque_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('mosques', 'id'),
            ],
            'image' => 'sometimes|nullable|file|image|mimes:jpg,jpeg,png,webp|max:8192',
            'role_id' => [
                'sometimes',
                'nullable',
                Rule::exists('roles', 'id')->where(
                    fn ($query) => $query->where('role_family', RoleFamily::Student->value)
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('username') && is_string($this->input('username'))) {
            $this->merge([
                'username' => strtolower($this->input('username')),
            ]);
        }
    }

    public function messages()
    {
        return [
            'first_name.required' => 'الاسم الأول مطلوب.',
            'last_name.required' => 'اسم العائلة مطلوب.',
            'username.required' => 'اسم المستخدم مطلوب.',
            'username.string' => 'اسم المستخدم يجب أن يكون نصاً.',
            'username.min' => 'يجب ألا يقل اسم المستخدم عن 3 محارف.',
            'username.max' => 'يجب ألا يزيد اسم المستخدم على 30 محرفاً.',
            'username.regex' => 'اسم المستخدم يقبل الأحرف الإنجليزية والأرقام فقط، ويمكن استخدام النقطة أو الشرطة أو الشرطة السفلية بين أجزائه دون مسافات.',
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
            'username.unique' => 'اسم المستخدم مستخدم مسبقاً.',
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
