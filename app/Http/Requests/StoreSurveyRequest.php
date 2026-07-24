<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Foundation\Http\FormRequest;

class StoreSurveyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = [];
        $scheduleTimezone = (string) config('surveys.schedule_timezone', 'Asia/Damascus');

        foreach (['starts_at', 'ends_at'] as $field) {
            $value = $this->input($field);
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            try {
                $normalized[$field] = CarbonImmutable::parse($value, $scheduleTimezone)
                    ->utc()
                    ->format('Y-m-d\TH:i:s\Z');
            } catch (InvalidFormatException) {
                // Leave invalid input unchanged so the date validation rule reports it.
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'allow_multiple_responses' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الاستبيان مطلوب.',
            'logo.required' => 'يجب إضافة شعار أو صورة للاستبيان.',
            'logo.image' => 'ملف الشعار يجب أن يكون صورة صالحة.',
            'logo.max' => 'حجم صورة الاستبيان يجب ألا يتجاوز 5 ميغابايت.',
            'ends_at.after' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.',
        ];
    }
}
