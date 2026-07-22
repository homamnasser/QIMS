<?php

namespace App\Services;

use App\Enums\RoleFamily;
use App\IService\IRoleService;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoleService implements IRoleService
{
    public function getAllRoles(): Collection
    {
        return Role::with('permissions')->get();
    }

    public function getRoleById(int $roleId): ?Role
    {
        return Role::with('permissions')->find($roleId);
    }

    public function createRole(string $name, string $roleFamily, array $permissionIds): Role
    {
        return DB::transaction(function () use ($name, $roleFamily, $permissionIds) {
            $role = Role::create([
                'name' => $name,
                'guard_name' => 'web',
                'role_family' => $roleFamily,
                'is_system' => false,
                'suggested_role_family' => null,
                'role_family_reviewed_at' => now(),
            ]);

            $this->assignPermissionsToRole($role, $permissionIds, $roleFamily);

            return $role->load('permissions');
        });
    }

    public function assignPermissionsToRole(Role $role, array $permissionIds, ?string $roleFamily = null): void
    {
        $storedFamily = $role->role_family;
        $family = $roleFamily ?? ($storedFamily instanceof RoleFamily
            ? $storedFamily->value
            : (string) $storedFamily);
        $requiredNames = config("roles.family_permissions.{$family}", []);
        $requiredIds = Permission::query()
            ->whereIn('name', $requiredNames)
            ->pluck('id')
            ->all();

        $allPermissionIds = array_values(array_unique(array_merge(
            array_map('intval', $permissionIds),
            array_map('intval', $requiredIds)
        )));

        $role->syncPermissions($allPermissionIds);
    }

    public function updateRole(int $roleId, string $name, ?string $roleFamily, array $permissionIds): Role
    {
        return DB::transaction(function () use ($roleId, $name, $roleFamily, $permissionIds) {
            $role = Role::findOrFail($roleId);
            $storedFamily = $role->role_family;
            $effectiveFamily = $roleFamily ?? ($storedFamily instanceof RoleFamily
                ? $storedFamily->value
                : (string) $storedFamily);

            $updates = ['name' => $name];
            if ($roleFamily !== null) {
                $updates['role_family'] = $roleFamily;
                $updates['role_family_reviewed_at'] = now();
            }

            $role->update($updates);

            $this->assignPermissionsToRole($role, $permissionIds, $effectiveFamily);

            return $role->load('permissions');
        });
    }

    public function deleteRole(int $roleId): bool
    {
        $role = Role::findOrFail($roleId);

        return $role->delete();
    }

    public function getAllPermissions(): Collection
    {
        return Permission::all();
    }
}
