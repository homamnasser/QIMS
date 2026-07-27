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

        $permissions = collect(config('roles.student_capabilities'))
            ->map(fn (string $name): Permission => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        Role::query()
            ->where('guard_name', 'web')
            ->where('role_family', RoleFamily::Student->value)
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', array_values(config('roles.student_capabilities')))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
