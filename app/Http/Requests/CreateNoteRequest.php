<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class CreateNoteRequest extends FormRequest
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
            'student_id'  => 'required|integer|exists:students,id',
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
        ];
    }
    public function messages(): array
    {
        return [
            'student_id.required' => "معرّف الطالب مطلوب.",
            'student_id.integer'  => "معرّف الطالب يجب أن يكون عدداً صحيحاً.",
            'student_id.exists'   => "الطالب المحدد غير موجود.",
            'title.required'      => "عنوان الملاحظة مطلوب.",
            'title.string'        => "عنوان الملاحظة يجب أن يكون نصاً.",
            'title.max'           => "عنوان الملاحظة يجب ألا يتجاوز 255 حرفاً.",
            'description.required' => "وصف الملاحظة مطلوب.",
            'description.string'   => "وصف الملاحظة يجب أن يكون نصاً.",
        ];
    }
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => 422,
            'status'  => 'error',
            'errors'  => $validator->errors()
        ], 422));
    }
}
