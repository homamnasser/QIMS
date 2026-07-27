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

        $studentRecordPermissions = collect(config('roles.student_capabilities'))
            ->only(['notes', 'sabrs', 'memorizations', 'warnings', 'exams'])
            ->map(fn (string $name): Permission => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        $legacyPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn(
                'name',
                array_values(config('roles.legacy_student_capabilities'))
            )
            ->get();

        Role::query()
            ->where('guard_name', 'web')
            ->where('role_family', RoleFamily::Student->value)
            ->each(function (Role $role) use (
                $studentRecordPermissions,
                $legacyPermissions
            ): void {
                $role->givePermissionTo($studentRecordPermissions);
                $role->revokePermissionTo($legacyPermissions);
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $studentRecordPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn(
                'name',
                collect(config('roles.student_capabilities'))
                    ->only(['notes', 'sabrs', 'memorizations', 'warnings', 'exams'])
                    ->values()
            )
            ->get();
        $legacyPermissions = collect(config('roles.legacy_student_capabilities'))
            ->map(fn (string $name): Permission => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        Role::query()
            ->where('guard_name', 'web')
            ->where('role_family', RoleFamily::Student->value)
            ->each(function (Role $role) use (
                $studentRecordPermissions,
                $legacyPermissions
            ): void {
                $role->revokePermissionTo($studentRecordPermissions);
                $role->givePermissionTo($legacyPermissions);
            });

        Permission::query()
            ->whereKey($studentRecordPermissions->modelKeys())
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
