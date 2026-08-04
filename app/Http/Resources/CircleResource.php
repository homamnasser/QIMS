<?php

namespace App\Http\Resources;

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
            'id' => $this->id,
            'name' => $this->name,
            'teacher_id' => $this->teacher_id,
            'course_id' => $this->course_id,
            'quran_mode' => $this->quran_mode->value,
            'quran_mode_label' => $this->quran_mode->label(),
            'teacher' => [
                'id' => $this->teacher->id ?? null,
                'name' => $this->teacher ? ($this->teacher->first_name.' '.$this->teacher->last_name) : 'N/A',
            ],
            'course' => [
                'id' => $this->course->id ?? null,
                'name' => $this->course->name ?? 'N/A',
            ],
            'mosque' => [
                'id' => $this->course?->mosque?->id ?? null,
                'name' => $this->course?->mosque?->name ?? 'غير معين',
            ],
            // يظهر فقط عند withCount('students')، فلا يكلّف استعلاماً في المسارات القديمة.
            'students_count' => $this->whenCounted('students'),
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}
