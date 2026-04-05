<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'supervisor_details' => [
                'id'    => $this->supervisorUser->id,
                'name'  => $this->supervisorUser->first_name . ' ' . $this->supervisorUser->last_name,
                'email' => $this->supervisorUser->email,
            ],
            'logo_url'    => $this->logo ? asset('storage/' . $this->logo) : null,
            'created_at'  => $this->created_at->format('Y-m-d'),
            'is_active'   => $this->is_active,
        ];
    }
}
