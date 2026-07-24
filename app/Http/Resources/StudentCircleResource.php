<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentCircleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,       // معرف سجل العلاقة نفسه

            'circle_id' => $this->circle,   // معرف الحلقة من قاعدة البيانات
            'circle_name' => $this->circleDetails ? $this->circleDetails->name : null, // جلب اسم الحلقة عبر العلاقة

            'student' => [
                'id' => $this->studentDetails ? $this->studentDetails->id : null,
                'selfnumber' => $this->studentDetails?->selfnumber,
                'full_name' => $this->studentDetails
                    ? ($this->studentDetails->first_name.' '.$this->studentDetails->last_name)
                    : null,
            ],
        ];
    }
}
