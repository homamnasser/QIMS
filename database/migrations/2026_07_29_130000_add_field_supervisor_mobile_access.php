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

        $permissions = collect(config('roles.field_supervisor_permissions'))
            ->map(fn (string $name): Permission => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        $role = Role::query()
            ->where('name', 'field-supervisor')
            ->where('guard_name', 'web')
            ->first();

        if ($role && $role->role_family !== RoleFamily::FieldSupervisor) {
            throw new RuntimeException(
                'Cannot create the protected field-supervisor role because that name is already in use.'
            );
        }

        $role ??= Role::create([
            'name' => 'field-supervisor',
            'guard_name' => 'web',
            'role_family' => RoleFamily::FieldSupervisor->value,
            'is_system' => true,
            'suggested_role_family' => null,
            'role_family_reviewed_at' => now(),
        ]);

        $role->forceFill([
            'role_family' => RoleFamily::FieldSupervisor->value,
            'is_system' => true,
            'suggested_role_family' => null,
            'role_family_reviewed_at' => $role->role_family_reviewed_at ?? now(),
        ])->save();
        $role->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()
            ->where('name', 'field-supervisor')
            ->where('guard_name', 'web')
            ->where('role_family', RoleFamily::FieldSupervisor->value)
            ->delete();

        Permission::query()
            ->where('name', config('roles.capabilities.field_operations'))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
