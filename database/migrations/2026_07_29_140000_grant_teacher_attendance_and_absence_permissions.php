<?php

use App\Enums\RoleFamily;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(config('roles.attendance_and_absence_permissions'))
            ->mapWithKeys(function (string $name): array {
                $permission = Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);

                return [$name => $permission];
            });

        $teacherRole = Role::query()
            ->where('name', 'teacher')
            ->where('guard_name', 'web')
            ->where('role_family', RoleFamily::Teacher->value)
            ->where('is_system', true)
            ->first();

        if (! $teacherRole) {
            return;
        }

        $teacherRole->givePermissionTo(
            $permissions->only(config('roles.teacher_attendance_and_absence_permissions'))
        );

        $permissions
            ->except(config('roles.teacher_attendance_and_absence_permissions'))
            ->each(fn (Permission $permission) => $teacherRole->revokePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $listPermission = Permission::query()
            ->where('name', 'عرض كافة الغيابات')
            ->where('guard_name', 'web')
            ->first();

        $teacherRole = Role::query()
            ->where('name', 'teacher')
            ->where('guard_name', 'web')
            ->where('role_family', RoleFamily::Teacher->value)
            ->where('is_system', true)
            ->first();

        if ($teacherRole && $listPermission) {
            $teacherRole->revokePermissionTo($listPermission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
