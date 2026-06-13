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
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,

            'date'        => $this->created_at ? $this->created_at->format('Y-m-d') : null,

            'student'     => [
                'id'        => $this->student,
                'full_name' => $this->studentDetails
                    ? ($this->studentDetails->first_name . ' ' . $this->studentDetails->last_name)
                    : null,
            ],

            'warner'      => [
                'id'        => $this->warner,
                'full_name' => $this->warnerDetails
                    ? ($this->warnerDetails->first_name . ' ' . $this->warnerDetails->last_name)
                    : null,
            ],
        ];
    }
}
