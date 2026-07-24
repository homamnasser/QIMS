<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SabrResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sabr_id' => $this->id,
            'value' => $this->value,
            'type' => $this->type,
            'date' => $this->date ? $this->date->format('Y-m-d') : null,
            'parts' => $this->parts,
            'note' => $this->note,

            'student' => [
                'id' => $this->studentDetails ? $this->studentDetails->id : null,
                'selfnumber' => $this->studentDetails?->selfnumber,
                'full_name' => $this->studentDetails
                    ? ($this->studentDetails->first_name.' '.$this->studentDetails->last_name)
                    : null,
            ],

            'giver' => [
                'id' => $this->giverDetails ? $this->giverDetails->id : null,
                'full_name' => $this->giverDetails
                    ? ($this->giverDetails->first_name.' '.$this->giverDetails->last_name)
                    : null,
            ],

            'giver_details' => [
                'id' => $this->giverDetails ? $this->giverDetails->id : null,
                'first_name' => $this->giverDetails ? $this->giverDetails->first_name : null,
                'last_name' => $this->giverDetails ? $this->giverDetails->last_name : null,
            ],

            'course' => [
                'id' => $this->courseDetails ? $this->courseDetails->id : null,
                'title' => $this->courseDetails ? $this->courseDetails->name : null,
            ],

            'course_details' => [
                'id' => $this->courseDetails ? $this->courseDetails->id : null,
                'name' => $this->courseDetails ? $this->courseDetails->name : null,
            ],

            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
