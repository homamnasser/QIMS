<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Models\Role;
use Database\Seeders\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * الحد الأدنى لكل عائلة هو العقد الوحيد الذي يحكم ما يستطيع الدور فعله.
 * هذا الملف يثبّت محتوى ذلك العقد وآلية تطبيقه، لا تفاصيل كل مسار.
 */
class RoleMinimumPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_family_minimum_includes_login_and_logout(): void
    {
        foreach (config('roles.family_permissions') as $family => $minimum) {
            foreach (config('roles.authentication_permissions') as $permission) {
                $this->assertContains(
                    $permission,
                    $minimum,
                    "العائلة {$family} تفتقد صلاحية {$permission}."
                );
            }
        }
    }

    /**
     * بطاقة التقييم النهائي على الويب تمر بثلاث بوابات: القائمة والبطاقة
     * («عرض دورات التقييم»)، ولوحة الجاهزية («عرض تقييمات الطلاب النهائية»)،
     * ثم الحفظ («إدخال تقييم المدرس»). امتلاك الأخيرة وحدها يترك المعلم أمام باب مغلق.
     */
    public function test_teacher_minimum_opens_the_web_final_evaluation_card(): void
    {
        $minimum = config('roles.family_permissions.'.RoleFamily::Teacher->value);

        foreach ([
            'عرض دورات التقييم',
            'عرض تقييمات الطلاب النهائية',
            'إدخال تقييم المدرس',
            'إدخال تقييم القرآن',
        ] as $permission) {
            $this->assertContains($permission, $minimum);
        }
    }

    public function test_field_supervisor_minimum_is_attendance_and_its_associations_only(): void
    {
        $minimum = config('roles.family_permissions.'.RoleFamily::FieldSupervisor->value);

        $this->assertEqualsCanonicalizing([
            'تسجيل الدخول',
            'تسجيل الخروج',
            'عرض كافة الحلقات',
            'عرض تفاصيل الحلقة',
            'عرض طلاب الحلقة',
            'عرض كافة الطلاب',
            'عرض تفاصيل الطالب',
            'عرض تواريخ الكورس',
            ...config('roles.attendance_and_absence_permissions'),
        ], $minimum);

        // قدرة تخطي حصر السجلات بأصحابها تفتح التقييم والملاحظات والسبر كاملة.
        $this->assertNotContains(config('roles.capabilities.field_operations'), $minimum);

        foreach (config('evaluation.permissions') as $permission) {
            $this->assertNotContains($permission, $minimum);
        }
    }

    public function test_student_minimum_is_login_logout_and_self_service_only(): void
    {
        $this->assertEqualsCanonicalizing(
            [
                ...config('roles.authentication_permissions'),
                ...array_values(config('roles.student_capabilities')),
            ],
            config('roles.family_permissions.'.RoleFamily::Student->value)
        );
    }

    public function test_seeded_protected_roles_match_their_family_minimum_exactly(): void
    {
        $this->seed(TestDataSeeder::class);

        foreach ([
            'field-supervisor' => RoleFamily::FieldSupervisor,
            'teacher' => RoleFamily::Teacher,
            'student' => RoleFamily::Student,
        ] as $name => $family) {
            $role = Role::where('name', $name)->firstOrFail();

            $this->assertEqualsCanonicalizing(
                config('roles.family_permissions.'.$family->value),
                $role->permissions->pluck('name')->all(),
                "الدور {$name} لا يطابق الحد الأدنى لعائلته."
            );
        }
    }

    public function test_sync_command_restores_drifted_system_roles_and_spares_custom_ones(): void
    {
        $this->seed(TestDataSeeder::class);

        $extra = Permission::findOrCreate('صلاحية إضافية للاختبار', 'web');
        $systemTeacher = Role::where('name', 'teacher')->firstOrFail();
        $systemTeacher->givePermissionTo($extra);
        $systemTeacher->revokePermissionTo('إدخال تقييم المدرس');

        $customTeacher = Role::create([
            'name' => 'معلم فرع حلب',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Teacher->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);
        $customTeacher->givePermissionTo($extra);

        Artisan::call('roles:sync-minimums');

        // دور النظام يعود إلى حدّه الأدنى تماماً: يستعيد الناقص ويفقد الزائد.
        $this->assertEqualsCanonicalizing(
            config('roles.family_permissions.'.RoleFamily::Teacher->value),
            $systemTeacher->fresh()->permissions->pluck('name')->all()
        );

        // الدور المخصّص يكتسب الحد الأدنى ويحتفظ بما منحه المدير فوقه.
        $customPermissions = $customTeacher->fresh()->permissions->pluck('name');
        foreach (config('roles.family_permissions.'.RoleFamily::Teacher->value) as $permission) {
            $this->assertContains($permission, $customPermissions);
        }
        $this->assertContains($extra->name, $customPermissions);
    }

    public function test_sync_command_is_idempotent(): void
    {
        $this->seed(TestDataSeeder::class);

        Artisan::call('roles:sync-minimums');
        $before = Role::with('permissions')->get()
            ->mapWithKeys(fn (Role $role) => [
                $role->name => $role->permissions->pluck('name')->sort()->values()->all(),
            ]);

        Artisan::call('roles:sync-minimums');
        $after = Role::with('permissions')->get()
            ->mapWithKeys(fn (Role $role) => [
                $role->name => $role->permissions->pluck('name')->sort()->values()->all(),
            ]);

        $this->assertSame($before->all(), $after->all());
    }
}
