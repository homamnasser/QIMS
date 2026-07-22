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
        $missingColumns = array_values(array_filter([
            'role_family',
            'is_system',
            'suggested_role_family',
            'role_family_reviewed_at',
        ], fn (string $column): bool => ! Schema::hasColumn($rolesTable, $column)));

        if ($missingColumns === []) {
            return;
        }

        if (in_array('role_family', $missingColumns, true)) {
            Schema::table($rolesTable, function (Blueprint $table): void {
                $table->string('role_family', 32)->default(RoleFamily::Custom->value)->index();
            });
        }

        if (in_array('is_system', $missingColumns, true)) {
            Schema::table($rolesTable, function (Blueprint $table): void {
                $table->boolean('is_system')->default(false)->index();
            });
        }

        if (in_array('suggested_role_family', $missingColumns, true)) {
            Schema::table($rolesTable, function (Blueprint $table): void {
                $table->string('suggested_role_family', 32)->nullable();
            });
        }

        if (in_array('role_family_reviewed_at', $missingColumns, true)) {
            Schema::table($rolesTable, function (Blueprint $table): void {
                $table->timestamp('role_family_reviewed_at')->nullable();
            });
        }

        $protected = [
            'super-admin' => RoleFamily::SuperAdmin->value,
            'admin' => RoleFamily::Admin->value,
            'supervisor' => RoleFamily::Supervisor->value,
            'teacher' => RoleFamily::Teacher->value,
            'student' => RoleFamily::Student->value,
        ];
        $capabilityId = DB::table($tableNames['permissions'])
            ->where('name', config('roles.capabilities.supervise'))
            ->where('guard_name', 'web')
            ->value('id');

        foreach (DB::table($rolesTable)->select(['id', 'name', 'role_family'])->get() as $role) {
            if (array_key_exists($role->name, $protected)) {
                DB::table($rolesTable)->where('id', $role->id)->update([
                    'role_family' => $protected[$role->name],
                    'is_system' => true,
                    'suggested_role_family' => null,
                    'role_family_reviewed_at' => now(),
                ]);

                continue;
            }

            $wasAutomaticallyPrivileged = in_array($role->role_family, [
                RoleFamily::Admin->value,
                RoleFamily::Supervisor->value,
            ], true);
            $suggestedFamily = $this->suggestFamily($role->name);

            DB::table($rolesTable)->where('id', $role->id)->update([
                'role_family' => RoleFamily::Custom->value,
                'is_system' => false,
                'suggested_role_family' => $suggestedFamily === RoleFamily::Custom->value
                    ? null
                    : $suggestedFamily,
                'role_family_reviewed_at' => null,
            ]);

            if ($capabilityId && $wasAutomaticallyPrivileged) {
                DB::table($tableNames['role_has_permissions'])
                    ->where('role_id', $role->id)
                    ->where('permission_id', $capabilityId)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // Deliberately non-destructive: a repair rollback must not remove
        // columns owned by earlier migrations or re-grant revoked privileges.
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
