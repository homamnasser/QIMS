<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbsenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'note' => $this->note,
            'date' => $this->date ? $this->date->format('Y-m-d') : null,

            'student' => [
                'id' => $this->studentDetails ? $this->studentDetails->id : null,
                'selfnumber' => $this->studentDetails?->selfnumber,
                'full_name' => $this->studentDetails
                    ? trim($this->studentDetails->first_name.' '.$this->studentDetails->last_name)
                    : null,
            ],

            'course' => [
                'id' => $this->courseDetails ? $this->courseDetails->id : null,
                'name' => $this->courseDetails ? $this->courseDetails->name : null,
            ],

            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
        ];
    }
}
