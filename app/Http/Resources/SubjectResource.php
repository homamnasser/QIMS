<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'marks' => [
                'min' => $this->min_marks,
                'max' => $this->max_marks,
            ],
            'course' => $this->course?->name,
            'course_id' => $this->course_id,
            'pdf_url' => $this->pdf_path ? asset('storage/' . $this->pdf_path) : null,
            'shared_with' => $this->sharedSubject?->name,
            'shared_with_subject_id' => $this->shared_with_subject_id,
        ];
    }
}
