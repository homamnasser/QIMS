<?php

namespace App\Console\Commands;

use App\Models\Role;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * يعيد تطبيق الحد الأدنى لكل عائلة على الأدوار القائمة.
 *
 * قبل هذا الأمر كان كل تعديل على `config/roles.php` يحتاج هجرة مخصّصة تكرّر
 * المنطق نفسه، وكانت أدوار النظام المحميّة من التعديل عبر الواجهة تبقى على
 * حالتها القديمة حتى تُكتب تلك الهجرة. الآن مصدر الحقيقة واحد، والتطبيق أمر
 * واحد قابل للتكرار بلا أثر جانبي.
 *
 * القاعدة: عائلات `exact_families` يُزامَن دورها النظامي على الحد الأدنى تماماً
 * فيُسحب منها ما زاد عنه؛ وما عداها — والأدوار المخصّصة كافة — يُضاف إليها
 * الحد الأدنى دون المساس بما منحه المدير فوقه.
 */
class SyncRoleMinimumPermissions extends Command
{
    protected $signature = 'roles:sync-minimums {--dry-run : اعرض التغييرات دون تطبيقها}';

    protected $description = 'مزامنة أدوار النظام والأدوار المخصّصة مع الحد الأدنى لصلاحيات عائلاتها.';

    public function handle(): int
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $familyMinimums = config('roles.family_permissions', []);
        $exactFamilies = config('roles.exact_families', []);
        $dryRun = (bool) $this->option('dry-run');

        foreach ($familyMinimums as $family => $minimum) {
            $permissions = collect($minimum)
                ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

            $roles = Role::query()
                ->where('guard_name', 'web')
                ->where('role_family', $family)
                ->get();

            foreach ($roles as $role) {
                // الحذف يقتصر على أدوار النظام في العائلات المضبوطة: الدور
                // المخصّص قد يحمل صلاحيات إضافية منحها المدير عن قصد.
                $exact = $role->is_system && in_array($family, $exactFamilies, true);

                $current = $role->permissions->pluck('name');
                $target = $exact
                    ? $permissions->pluck('name')
                    : $current->merge($permissions->pluck('name'))->unique();

                $added = $target->diff($current)->values();
                $removed = $current->diff($target)->values();

                if ($added->isEmpty() && $removed->isEmpty()) {
                    continue;
                }

                $this->line(sprintf(
                    '%s (%s): +%d / -%d',
                    $role->name,
                    $family,
                    $added->count(),
                    $removed->count()
                ));
                $added->each(fn (string $name) => $this->line("    + {$name}"));
                $removed->each(fn (string $name) => $this->line("    - {$name}"));

                if (! $dryRun) {
                    $role->syncPermissions($target->all());
                }
            }
        }

        $registrar->forgetCachedPermissions();

        $this->info($dryRun ? 'عرض فقط — لم يُطبَّق أي تغيير.' : 'اكتملت مزامنة الحدود الدنيا.');

        return self::SUCCESS;
    }
}
