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

        foreach (config('evaluation.permissions') as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        $studentPermissions = collect([
            config('roles.student_capabilities.final_results'),
            config('roles.student_capabilities.certificates'),
        ])->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::query()
            ->where('guard_name', 'web')
            ->where('role_family', RoleFamily::Student->value)
            ->each(fn (Role $role) => $role->givePermissionTo($studentPermissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                ...config('evaluation.permissions'),
                config('roles.student_capabilities.final_results'),
                config('roles.student_capabilities.certificates'),
            ])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
