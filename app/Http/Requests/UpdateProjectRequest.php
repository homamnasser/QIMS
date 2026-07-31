<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
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
            'name' => [
                'sometimes',
                'string',
                'max:255',
                'unique:projects,name,'.$this->route('id'),
            ],

            'description' => 'sometimes|string',
            'audience' => 'sometimes|string',

            'supervisor' => [
                'sometimes',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if ($user && ! $user->canSupervise()) {
                        $fail('المستخدم المحدد يجب أن يملك صلاحية الإشراف.');
                    }
                },
            ],

            'logo' => 'sometimes|nullable|file|image|mimes:jpg,jpeg,png,webp|max:8192',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'اسم المشروع مستخدم مسبقاً، يرجى اختيار اسم آخر.',
            'name.max' => 'اسم المشروع يجب ألا يتجاوز 255 حرفاً.',
            'name.string' => 'اسم المشروع يجب أن يكون نصاً صالحاً.',

            'description.string' => 'الوصف يجب أن يكون نصاً صالحاً.',
            'audience.string' => 'حقل الفئة المستهدفة يجب أن يكون نصاً صالحاً.',

            'supervisor.exists' => 'المشرف المحدد غير موجود في سجلاتنا.',

            'logo.image' => 'يجب أن يكون شعار المشروع صورة صالحة.',
            'logo.mimes' => 'يجب أن يكون شعار المشروع بصيغة JPG أو PNG أو WebP.',
            'logo.max' => 'حجم شعار المشروع يجب ألا يتجاوز 8 ميغابايت.',
            'logo.file' => 'الشعار يجب أن يكون ملفاً مرفوعاً صالحاً.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 422,
            'message' => $validator->errors(),
        ], 422));
    }
}
