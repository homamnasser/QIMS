<?php

use App\Enums\RoleFamily;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * فتح سجل الحضور والغياب أمام الطالب في التطبيق.
 *
 * كان الطالب يُرصد له الحضور ولا يملك أي واجهة يراه فيها، فكان إشعار الغياب
 * بلا وجهة يفتحها. هذه الهجرة تنشئ قدرة الطالب وتسندها لأدوار عائلة الطلاب.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate(
            config('roles.student_capabilities.attendance'),
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
            ->where('name', config('roles.student_capabilities.attendance'))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
