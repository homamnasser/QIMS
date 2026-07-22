<?php

namespace App\IService;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

interface IRoleService
{
    /**
     * Get all roles.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role>
     */
    public function getAllRoles(): Collection;

    public function getRoleById(int $roleId): ?Role;

    public function createRole(string $name, string $roleFamily, array $permissionIds): Role;

    public function updateRole(int $roleId, string $name, ?string $roleFamily, array $permissionIds): Role;

    public function deleteRole(int $roleId): bool;

    public function getAllPermissions(): Collection;
}
