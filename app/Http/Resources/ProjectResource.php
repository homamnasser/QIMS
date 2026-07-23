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
            'logo_url'    => $this->formatImageUrl($this->logo),
            'audience'    => $this->audience,
            'created_at'  => $this->created_at->format('Y-m-d'),
            'is_active'   => $this->is_active,
        ];
    }

    private function formatImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $cleanPath = ltrim($path, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            return asset($cleanPath);
        }

        return asset('storage/' . $cleanPath);
    }
}
