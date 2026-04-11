<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;



class CourseResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            // CourseResource.php
            'dates' => [
                'start_date' => $this->start_date instanceof \Carbon\Carbon ? $this->start_date->format('Y-m-d') : $this->start_date,
                'end_date'   => $this->end_date instanceof \Carbon\Carbon ? $this->end_date->format('Y-m-d') : $this->end_date,
                'is_expired' => now()->gt($this->end_date),
            ],

            'mosque'      => $this->mosque?->name,

            'project'     => $this->project?->name,

            'parent' => $this->parentCourse?->name,

            'created_at'  => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
