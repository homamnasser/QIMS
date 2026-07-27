<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentLearningCircleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'teacher' => [
                'id' => $this->teacher?->id,
                'name' => $this->teacher
                    ? trim($this->teacher->first_name.' '.$this->teacher->last_name)
                    : null,
            ],
            'course' => [
                'id' => $this->course?->id,
                'name' => $this->course?->name,
            ],
            'mosque' => [
                'id' => $this->course?->mosque?->id,
                'name' => $this->course?->mosque?->name,
            ],
        ];
    }
}
