<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CircleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'teacher_id' => $this->teacher_id,
            'course_id'  => $this->course_id,
            'teacher'    => [
                'id'   => $this->teacher->id ?? null,
                'name' => $this->teacher ? ($this->teacher->first_name . ' ' . $this->teacher->last_name) : 'N/A',
            ],
            'course'     => [
                'id'   => $this->course->id ?? null,
                'name' => $this->course->name ?? 'N/A',
            ],
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}
