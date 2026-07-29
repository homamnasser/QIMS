<?php

use App\Enums\RoleFamily;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const TEACHER_PERMISSIONS = [
        'إدخال تقييم المدرس',
        'إدخال تقييم القرآن',
    ];

    private const OBSOLETE_MANUAL_PERMISSIONS = [
        'إدخال تقييم القراءة',
        'إدخال نتائج الامتحانات النظرية',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(self::TEACHER_PERMISSIONS)
            ->map(fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        Role::query()
            ->where('guard_name', 'web')
            ->where('role_family', RoleFamily::Teacher->value)
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', self::OBSOLETE_MANUAL_PERMISSIONS)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::OBSOLETE_MANUAL_PERMISSIONS as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        Role::query()
            ->where('guard_name', 'web')
            ->where('role_family', RoleFamily::Teacher->value)
            ->each(fn (Role $role) => $role->revokePermissionTo(self::TEACHER_PERMISSIONS));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
