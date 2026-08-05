<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReadingImprovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $typeLabels = [
            'significant_improvement' => 'تحسن معتبر',
            'slight_improvement'      => 'تحسن بسيط',
            'no_improvement'          => 'عدم تحسن',
            'decline'                 => 'تراجع',
        ];

        return [
            'id'           => $this->id,
            'student_id'   => $this->student,
            'full_name' => $this->studentDetails
                    ? ($this->studentDetails->first_name.' '.$this->studentDetails->last_name)
                    : null,
            'course_id'    => $this->course,
            'course_name'  => $this->courseDetails?->name,
            'type'         => $this->type,
            'type_label'   => $typeLabels[$this->type] ?? $this->type,
            'description'  => $this->description,
            // تاريخ التقييم القرائي كما جرى؛ هو ما تُقارَن به نافذة الدورة.
            'occurred_at'  => ($this->occurred_at ?? $this->created_at)?->format('Y-m-d'),
            'created_at'   => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at'   => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
    }
