<?php

namespace App\Services;

use App\IService\IRoleService;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
            // الخطوة 1: إنشاء الدور فقط
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);

            // الخطوة 2: إسناد الصلاحيات عبر تابع منفصل
            $this->assignPermissionsToRole($role, $permissionIds);

            return $role;
        });
    }

    /** تابع منفصل ومستقل لإسناد الصلاحيات */
    public function assignPermissionsToRole(Role $role, array $permissionIds): void
    {
        // استخدام syncPermissions يضمن مسح الصلاحيات القديمة ووضع الجديدة
        // التأكد من أن القيم أرقام (Integers) لتجنب خطأ Spatie
        $role->syncPermissions(array_map('intval', $permissionIds));
    }

    /** تحديث الدور */
    public function updateRole(int $roleId, string $name, array $permissionIds): Role
    {
        return DB::transaction(function () use ($roleId, $name, $permissionIds) {
            $role = Role::findOrFail($roleId);

            // تحديث الاسم
            $role->update(['name' => $name]);

            // إسناد الصلاحيات عبر التابع المنفصل
            $this->assignPermissionsToRole($role, $permissionIds);

            return $role;
        });
    }

    public function deleteRole(int $roleId): bool
    {
        $role = Role::findOrFail($roleId);
        return $role->delete();
    }
}
