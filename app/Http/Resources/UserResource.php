<?php

namespace App\Http\Resources;

use App\Enums\RoleFamily;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        $role = $this->primaryRole();
        $roleFamily = $role?->role_family;

        return [
            'id'          => $this->id,
            'first_name'  => $this->first_name,
            'last_name'   => $this->last_name,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'birth_date'  => $this->birth_date,
            'role_id'     => $role?->id,
            'role'        => $role?->name,
            'role_family' => $roleFamily instanceof RoleFamily ? $roleFamily->value : $roleFamily,
            'account_type' => 'staff',
            'is_super_admin' => $this->isSuperAdmin(),
            'can_supervise' => $this->canSupervise(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'image_url'   => $this->formatImageUrl($this->image),
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
