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
            'username' => 'nullable|string|unique:students,username,'.$this->route('id'), // استثناء اليوزر نيم للطالب الحالي
            'mosque_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('mosques', 'id'),
            ],
            'image' => 'sometimes|nullable|file|image|mimes:jpg,jpeg,png|max:5120',
            'role_id' => [
                'sometimes',
                'nullable',
                Rule::exists('roles', 'id')->where(
                    fn ($query) => $query->where('role_family', RoleFamily::Student->value)
                ),
            ],
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
                'username' => strtolower($this->first_name.'.'.$this->last_name),
            ]);
        }
        // أما لو أرسل فقط الاسم الأول، نأخذ الاسم الأخير القديم من الموديل المخزن في السيرفر (إذا كان ممرراً عبر Route Model Binding)
        // ولتجنب التعقيد، يفضل دائماً إخبار فريق الفرونت إند بإرسال الحقلين معاً في حال الرغبة بتغيير أحدهما ليتم توليد الـ username بشكل صحيح ومترابط.
    }

    public function messages()
    {
        return [
            'first_name.required' => 'الاسم الأول مطلوب.',
            'last_name.required' => 'اسم العائلة مطلوب.',
            'phone_number.regex' => 'رقم الهاتف يجب أن يبدأ بـ 09 متبوعاً بـ 8 أرقام.',
            'phone_number.unique' => 'رقم الهاتف مستخدم مسبقاً.',
            'birth_date.required' => 'تاريخ الميلاد مطلوب.',
            'academic_class.required' => 'الصف الدراسي مطلوب.',
            'reading_level.required' => 'مستوى القراءة مطلوب.',
            'father_name.required' => 'اسم الأب مطلوب.',
            'parent_social_state.required' => 'الحالة الاجتماعية لولي الأمر مطلوبة.',
            'father_phone.required' => 'رقم هاتف الأب مطلوب.',
            'father_phone.regex' => 'رقم هاتف الأب يجب أن يبدأ بـ 09 متبوعاً بـ 8 أرقام.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'username.unique' => 'اسم المستخدم مُستخدم مسبقاً.',
            'mosque_id.exists' => 'المسجد المحدد غير موجود.',
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
