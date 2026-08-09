<?php

use App\Enums\RoleFamily;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * فتح تقييم التحسن في القراءة أمام تطبيق الجوال.
 *
 * المسارات نفسها موجودة على قناة الكادر الميداني (mobile/staff) بنفس أسماء
 * الصلاحيات، فلم يبقَ إلا إسناد الصلاحيات لأدوار الأساتذة القائمة، وإنشاء
 * صلاحية الطالب الجديدة لعرض تقييمات قراءته وحده.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $teacherPermissions = collect(config('roles.reading_improvement_permissions'))
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        Role::query()
            ->where('guard_name', 'web')
            ->where('role_family', RoleFamily::Teacher->value)
            ->each(fn (Role $role) => $role->givePermissionTo($teacherPermissions));

        $studentPermission = Permission::findOrCreate(
            config('roles.student_capabilities.reading_improvements'),
            'web'
        );

        Role::query()
            ->where('guard_name', 'web')
            ->where('role_family', RoleFamily::Student->value)
            ->each(fn (Role $role) => $role->givePermissionTo($studentPermission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // صلاحيات الكادر أنشأتها هجرة سابقة، فنكتفي بسحبها من أدوار الأساتذة.
        Role::query()
            ->where('guard_name', 'web')
            ->where('role_family', RoleFamily::Teacher->value)
            ->each(fn (Role $role) => $role->revokePermissionTo(
                config('roles.reading_improvement_permissions')
            ));

        Permission::query()
            ->where('guard_name', 'web')
            ->where('name', config('roles.student_capabilities.reading_improvements'))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
