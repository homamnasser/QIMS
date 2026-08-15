<?php

use App\Enums\RoleFamily;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * فتح صندوق الإشعارات أمام الطالب. نسخة من هجرة قدرة الحضور: القدرة تُنشأ
 * وتُسند لكل أدوار عائلة الطلاب القائمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate(
            config('roles.student_capabilities.notifications'),
            'web'
        );

        Role::query()
            ->where('guard_name', 'web')
            ->where('role_family', RoleFamily::Student->value)
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'web')
            ->where('name', config('roles.student_capabilities.notifications'))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
