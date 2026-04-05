<?php

namespace App\Services;

use App\IService\IRoleService;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;

class RoleService implements IRoleService
{
    public function getAllRoles(): Collection
    {
        return Role::with("permissions")->get();
    }

    public function getRoleById(int $roleId): ?Role
    {
        return Role::with("permissions")->find($roleId);
    }

    public function createRole(string $name, array $permissionIds): Role
    {
        return DB::transaction(function () use ($name, $permissionIds) {
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);

            $this->assignPermissionsToRole($role, $permissionIds);

            return $role;
        });
    }

    public function assignPermissionsToRole(Role $role, array $permissionIds): void
    {

        $role->syncPermissions(array_map('intval', $permissionIds));
    }

    public function updateRole(int $roleId, string $name, array $permissionIds): Role
    {
        return DB::transaction(function () use ($roleId, $name, $permissionIds) {
            $role = Role::findOrFail($roleId);

            $role->update(['name' => $name]);

            $this->assignPermissionsToRole($role, $permissionIds);

            return $role;
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
