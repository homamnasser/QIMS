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
        $hadSuggestionMetadata = Schema::hasColumn($rolesTable, 'suggested_role_family')
            && Schema::hasColumn($rolesTable, 'role_family_reviewed_at');

        if (! Schema::hasColumn($rolesTable, 'suggested_role_family')) {
            Schema::table($rolesTable, function (Blueprint $table): void {
                $table->string('suggested_role_family', 32)->nullable();
            });
        }

        if (! Schema::hasColumn($rolesTable, 'role_family_reviewed_at')) {
            Schema::table($rolesTable, function (Blueprint $table): void {
                $table->timestamp('role_family_reviewed_at')->nullable();
            });
        }

        // A fresh installation already received fail-closed data from the
        // preceding migration. This branch only repairs databases that ran the
        // earlier, permissive version before this hardening migration existed.
        if ($hadSuggestionMetadata) {
            return;
        }

        $protectedNames = config('roles.protected_names');
        $capabilityId = DB::table($tableNames['permissions'])
            ->where('name', config('roles.capabilities.supervise'))
            ->where('guard_name', 'web')
            ->value('id');

        $legacyRoles = DB::table($rolesTable)
            ->whereNotIn('name', $protectedNames)
            ->select(['id', 'name', 'role_family'])
            ->get();

        foreach ($legacyRoles as $role) {
            $suggestedFamily = $this->suggestFamily($role->name);
            $wasAutomaticallyPrivileged = in_array($role->role_family, [
                RoleFamily::Admin->value,
                RoleFamily::Supervisor->value,
            ], true);

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

        DB::table($rolesTable)
            ->whereIn('name', $protectedNames)
            ->update(['role_family_reviewed_at' => now()]);
    }

    public function down(): void
    {
        $rolesTable = config('permission.table_names.roles');
        $columns = array_values(array_filter([
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
