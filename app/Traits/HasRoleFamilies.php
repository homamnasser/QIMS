<?php

namespace App\Traits;

use App\Enums\RoleFamily;
use App\Models\Role;

trait HasRoleFamilies
{
    public function primaryRole(): ?Role
    {
        /** @var Role|null $role */
        $role = $this->roles()->orderBy('roles.id')->first();

        return $role;
    }

    public function hasRoleFamily(RoleFamily|string ...$families): bool
    {
        $values = array_map(
            static fn (RoleFamily|string $family): string => $family instanceof RoleFamily
                ? $family->value
                : $family,
            $families
        );

        return $this->roles()->whereIn('role_family', $values)->exists();
    }

    public function isSuperAdmin(): bool
    {
        // Root access intentionally remains tied to the single protected name.
        return $this->hasRole('super-admin');
    }
}
