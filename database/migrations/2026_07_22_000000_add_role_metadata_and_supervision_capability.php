<?php

use App\Enums\RoleFamily;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $rolesTable = $tableNames['roles'];
        $permissionsTable = $tableNames['permissions'];
        $rolePermissionsTable = $tableNames['role_has_permissions'];

        Schema::table($rolesTable, function (Blueprint $table): void {
            $table->string('role_family', 32)->default(RoleFamily::Custom->value)->index();
            $table->boolean('is_system')->default(false)->index();
            $table->string('suggested_role_family', 32)->nullable();
            $table->timestamp('role_family_reviewed_at')->nullable();
        });

        $protected = [
            'super-admin' => RoleFamily::SuperAdmin->value,
            'admin' => RoleFamily::Admin->value,
            'supervisor' => RoleFamily::Supervisor->value,
            'teacher' => RoleFamily::Teacher->value,
            'student' => RoleFamily::Student->value,
        ];

        // Existing custom names are never promoted automatically. The resolver
        // only records a non-authoritative suggestion for an administrator to
        // review; runtime authorization keeps the fail-closed `custom` family.
        foreach (DB::table($rolesTable)->select(['id', 'name'])->get() as $role) {
            $isSystem = array_key_exists($role->name, $protected);
            $family = $isSystem
                ? $protected[$role->name]
                : RoleFamily::Custom->value;
            $suggestedFamily = $isSystem ? null : $this->suggestFamily($role->name);

            DB::table($rolesTable)->where('id', $role->id)->update([
                'role_family' => $family,
                'is_system' => $isSystem,
                'suggested_role_family' => $suggestedFamily === RoleFamily::Custom->value
                    ? null
                    : $suggestedFamily,
                'role_family_reviewed_at' => $isSystem ? now() : null,
            ]);
        }

        $capability = config('roles.capabilities.supervise');
        $permissionId = DB::table($permissionsTable)
            ->where('name', $capability)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table($permissionsTable)->insertGetId([
                'name' => $capability,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $supervisoryRoleIds = DB::table($rolesTable)
            ->where('is_system', true)
            ->whereIn('role_family', [
                RoleFamily::SuperAdmin->value,
                RoleFamily::Admin->value,
                RoleFamily::Supervisor->value,
            ])
            ->pluck('id');

        foreach ($supervisoryRoleIds as $roleId) {
            DB::table($rolePermissionsTable)->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $capability = config('roles.capabilities.supervise');

        DB::table($tableNames['permissions'])
            ->where('name', $capability)
            ->where('guard_name', 'web')
            ->delete();

        $rolesTable = $tableNames['roles'];

        if (Schema::hasColumn($rolesTable, 'role_family')) {
            Schema::table($rolesTable, function (Blueprint $table): void {
                $table->dropIndex(['role_family']);
            });
        }

        if (Schema::hasColumn($rolesTable, 'is_system')) {
            Schema::table($rolesTable, function (Blueprint $table): void {
                $table->dropIndex(['is_system']);
            });
        }

        $columns = array_values(array_filter([
            'role_family',
            'is_system',
            'suggested_role_family',
            'role_family_reviewed_at',
        ], fn (string $column): bool => Schema::hasColumn($rolesTable, $column)));

        if ($columns !== []) {
            Schema::table($rolesTable, function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    private function suggestFamily(string $name): string
    {
        $normalized = mb_strtolower(trim($name), 'UTF-8');
        $normalized = str_replace('ـ', '', $normalized);
        $normalized = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $normalized) ?? $normalized;
        $normalized = strtr($normalized, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا']);
        $normalized = preg_replace('/[_\-.]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (array_intersect($tokens, ['غير', 'ليس', 'ليست', 'بدون', 'دون', 'لا', 'not', 'non', 'no', 'without'])) {
            return RoleFamily::Custom->value;
        }

        if (array_intersect($tokens, ['admin', 'administrator', 'اداري'])) {
            return RoleFamily::Admin->value;
        }

        if (array_intersect($tokens, ['supervisor', 'مشرف', 'مشرفون', 'مسؤول', 'مسئول', 'مسوول', 'مسؤولون', 'مدير', 'مدراء'])) {
            return RoleFamily::Supervisor->value;
        }

        if (array_intersect($tokens, ['teacher', 'instructor', 'معلم', 'مدرس', 'استاذ'])) {
            return RoleFamily::Teacher->value;
        }

        if (array_intersect($tokens, ['student', 'pupil', 'طالب', 'طلاب', 'طالبات', 'تلميذ', 'تلاميذ'])) {
            return RoleFamily::Student->value;
        }

        return RoleFamily::Custom->value;
    }
};
