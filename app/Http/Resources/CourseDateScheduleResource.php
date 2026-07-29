<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseDateScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'session_date' => $this->session_date->format('Y-m-d'),
            'course_id'    => $this->course_id,
            'lessons'      => LessonResource::collection($this->whenLoaded('lessons')),
            'created_at'   => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
