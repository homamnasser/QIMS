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
                    if ($user && !$user->canSupervise()) {
                        $fail('المستخدم المحدد يجب أن يملك صلاحية الإشراف.');
                    }
                },
            ],

            'logo'        => 'mimes:jpg,jpeg,png,pdf|max:5120',
        ];
    }


    public function messages(): array
    {
        return [
            'name.required'     => 'اسم المشروع مطلوب.',
            'name.unique'       => 'اسم المشروع مستخدم مسبقاً، يرجى اختيار اسم آخر.',
            'name.max'          => 'اسم المشروع يجب ألا يتجاوز 255 حرفاً.',

            'description.required' => 'يرجى إدخال وصف المشروع.',
            'audience.required'    => 'الفئة المستهدفة مطلوبة.',

            'supervisor.required' => 'يجب تعيين مشرف للمشروع.',
            'supervisor.exists'   => 'المشرف المحدد غير موجود في سجلاتنا.',

            'logo.mimes'         => 'الملف يجب أن يكون من نوع: ...',
            'logo.max'           => 'حجم الشعار يجب ألا يتجاوز 5 ميغابايت.',

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
