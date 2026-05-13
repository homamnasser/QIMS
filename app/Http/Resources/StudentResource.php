<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'username'       => $this->username,
            'academic_info'  => [
                'class'         => $this->academic_class,
                'reading_level' => $this->reading_level,
            ],
            'family_details' => [
                'father_name'         => $this->father_name,
                'parent_social_state' => $this->parent_social_state,
                'father_phone'        => $this->father_phone,
            ],
            'roles'          => $this->getRoleNames(),
            'created_at'     => $this->created_at->format('Y-m-d'),
        ];
    }
}
