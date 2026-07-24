<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarningResource extends JsonResource
{
    /**
     * تحويل البيانات الصادرة لـ JSON متناسق
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,

            'date' => $this->created_at ? $this->created_at->format('Y-m-d') : null,

            'student' => [
                'id' => $this->student,
                'selfnumber' => $this->studentDetails?->selfnumber,
                'full_name' => $this->studentDetails
                    ? ($this->studentDetails->first_name.' '.$this->studentDetails->last_name)
                    : null,
            ],

            'warner' => $this->warner,

            'warner_details' => [
                'id' => $this->warnerDetails ? $this->warnerDetails->id : null,
                'first_name' => $this->warnerDetails ? $this->warnerDetails->first_name : null,
                'last_name' => $this->warnerDetails ? $this->warnerDetails->last_name : null,
            ],

            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
        ];
    }
}
