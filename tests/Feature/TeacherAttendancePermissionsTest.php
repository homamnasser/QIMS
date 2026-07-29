<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAttendancePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_seed_grants_the_protected_teacher_only_the_allowed_attendance_actions(): void
    {
        $this->seed(TestDataSeeder::class);

        $teacherRole = Role::query()
            ->where('name', 'teacher')
            ->where('guard_name', 'web')
            ->firstOrFail();

        $this->assertTrue($teacherRole->is_system);
        $this->assertSame(RoleFamily::Teacher, $teacherRole->role_family);

        foreach (config('roles.teacher_attendance_and_absence_permissions') as $permission) {
            $this->assertTrue(
                $teacherRole->hasPermissionTo($permission),
                "Missing Teacher attendance permission: {$permission}"
            );
        }

        $this->assertFalse($teacherRole->hasPermissionTo('تعديل الغياب'));
        $this->assertFalse($teacherRole->hasPermissionTo('حذف غياب'));
    }

    public function test_migration_aligns_an_existing_protected_teacher_without_removing_unrelated_access(): void
    {
        $teacherRole = Role::create([
            'name' => 'teacher',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Teacher->value,
            'is_system' => true,
            'role_family_reviewed_at' => now(),
        ]);
        $unrelatedPermission = Permission::firstOrCreate([
            'name' => 'صلاحية معلم غير مرتبطة بالحضور',
            'guard_name' => 'web',
        ]);
        $teacherRole->givePermissionTo([
            $unrelatedPermission,
            Permission::findOrCreate('تعديل الغياب', 'web'),
            Permission::findOrCreate('حذف غياب', 'web'),
        ]);

        $migration = require database_path(
            'migrations/2026_07_29_140000_grant_teacher_attendance_and_absence_permissions.php'
        );
        $migration->up();

        $teacherRole->refresh();

        foreach (config('roles.teacher_attendance_and_absence_permissions') as $permission) {
            $this->assertTrue(
                $teacherRole->hasPermissionTo($permission),
                "Missing migrated Teacher attendance permission: {$permission}"
            );
        }

        $this->assertFalse($teacherRole->hasPermissionTo('تعديل الغياب'));
        $this->assertFalse($teacherRole->hasPermissionTo('حذف غياب'));
        $this->assertTrue($teacherRole->hasPermissionTo($unrelatedPermission));
    }
}
