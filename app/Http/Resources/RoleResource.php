<?php

namespace App\Http\Resources;

use App\Enums\RoleFamily;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleFamily = $this->role_family;
        $suggestedRoleFamily = $this->suggested_role_family;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'role_family' => $roleFamily instanceof RoleFamily ? $roleFamily->value : $roleFamily,
            'is_system' => (bool) $this->is_system,
            'suggested_role_family' => $suggestedRoleFamily instanceof RoleFamily
                ? $suggestedRoleFamily->value
                : $suggestedRoleFamily,
            'needs_role_family_review' => ! $this->is_system
                && $suggestedRoleFamily !== null
                && $this->role_family_reviewed_at === null,
            'role_family_reviewed_at' => $this->role_family_reviewed_at?->toISOString(),
            'permissions' => $this->permissions->pluck('name'),
        ];
    }
}
