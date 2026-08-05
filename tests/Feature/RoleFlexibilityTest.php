<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Http\Resources\StudentResource;
use App\IService\IRoleService;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleFlexibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_alias_receives_the_supervision_capability(): void
    {
        $role = app(IRoleService::class)->createRole(
            'مسؤول مشروع التحفيظ',
            RoleFamily::Supervisor->value,
            []
        );

        $this->assertSame(RoleFamily::Supervisor, $role->role_family);
        $this->assertFalse($role->is_system);
        $this->assertTrue($role->hasPermissionTo(config('roles.capabilities.supervise')));

        $user = User::create([
            'first_name' => 'أحمد',
            'last_name' => 'المشرف',
            'email' => 'flexible-supervisor@example.com',
            'phone' => '0999999999',
            'birth_date' => '1990-01-01',
            'password' => 'password123',
        ]);
        $user->syncRoles([$role]);

        $this->assertTrue($user->canSupervise());
    }

    public function test_student_account_identity_is_independent_from_its_role_name(): void
    {
        $role = Role::create([
            'name' => 'تلميذ متقدم',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Student->value,
            'is_system' => false,
        ]);

        $student = app(StudentService::class)->createStudent([
            'first_name' => 'محمد',
            'last_name' => 'الطالب',
            'username' => 'mohammad-student',
            'birth_date' => '2012-01-01',
            'academic_class' => 'السادس',
            'reading_level' => 'level_2',
            'father_name' => 'أحمد',
            'parent_social_state' => 'married',
            'father_phone' => '0999999998',
            'password' => 'password123',
            'role_id' => $role->id,
        ]);

        $resource = (new StudentResource($student))->resolve();

        $this->assertSame($role->id, $student->primaryRole()?->id);
        $this->assertSame('تلميذ متقدم', $resource['role']);
        $this->assertSame(RoleFamily::Student->value, $resource['role_family']);
        $this->assertSame('student', $resource['account_type']);
    }

    public function test_role_api_requires_an_explicit_family_instead_of_trusting_the_name(): void
    {
        $this->authenticateAsRoot();

        $permission = Permission::where('name', config('roles.capabilities.supervise'))->firstOrFail();

        $response = $this->postJson('/api/role/createRole', [
            'name' => 'مدير فرع دمشق',
            'permissions' => [$permission->id],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message.role_family.0', 'عائلة الدور مطلوبة.');

        $this->assertDatabaseMissing('roles', ['name' => 'مدير فرع دمشق']);
    }

    public function test_privileged_family_requires_confirmation_and_then_adds_its_capability(): void
    {
        $this->authenticateAsRoot();
        $permission = Permission::firstOrCreate([
            'name' => 'صلاحية اختبار آمنة',
            'guard_name' => 'web',
        ]);

        $payload = [
            'name' => 'مدير فرع دمشق',
            'role_family' => RoleFamily::Supervisor->value,
            'permissions' => [$permission->id],
        ];

        $this->postJson('/api/role/createRole', $payload)
            ->assertUnprocessable()
            ->assertJsonPath(
                'message.confirm_privileged_family.0',
                'يجب تأكيد منح الأهلية الإدارية أو الإشرافية لهذا الدور.'
            );

        $response = $this->postJson('/api/role/createRole', [
            ...$payload,
            'confirm_privileged_family' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.role_family', RoleFamily::Supervisor->value)
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.needs_role_family_review', false);

        $this->assertDatabaseHas('roles', [
            'name' => 'مدير فرع دمشق',
            'role_family' => RoleFamily::Supervisor->value,
            'is_system' => false,
        ]);

        $role = Role::where('name', 'مدير فرع دمشق')->firstOrFail();
        $this->assertNotNull($role->role_family_reviewed_at);
        $this->assertTrue($role->hasPermissionTo(config('roles.capabilities.supervise')));
    }

    public function test_created_field_supervisor_requires_confirmation_and_keeps_all_operational_permissions(): void
    {
        $this->authenticateAsRoot();
        $permission = Permission::firstOrCreate([
            'name' => 'صلاحية إضافية للمشرف الميداني',
            'guard_name' => 'web',
        ]);
        $payload = [
            'name' => 'مشرف ميداني فرع دمشق',
            'role_family' => RoleFamily::FieldSupervisor->value,
            'permissions' => [$permission->id],
        ];

        $this->postJson('/api/role/createRole', $payload)
            ->assertUnprocessable()
            ->assertJsonPath(
                'message.confirm_privileged_family.0',
                'يجب تأكيد منح الأهلية الإدارية أو الإشرافية لهذا الدور.'
            );

        $this->postJson('/api/role/createRole', [
            ...$payload,
            'confirm_privileged_family' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.role_family', RoleFamily::FieldSupervisor->value);

        $role = Role::where('name', 'مشرف ميداني فرع دمشق')->firstOrFail();

        foreach (config('roles.field_supervisor_permissions') as $requiredPermission) {
            $this->assertTrue(
                $role->hasPermissionTo($requiredPermission),
                "Missing required Field Supervisor permission: {$requiredPermission}"
            );
        }

        $this->assertTrue($role->hasPermissionTo($permission));
    }

    public function test_teacher_family_selects_and_enforces_its_minimum_permissions(): void
    {
        $this->authenticateAsRoot();
        $minimumPermissions = config('roles.family_permissions.'.RoleFamily::Teacher->value);

        foreach ($minimumPermissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $extraPermission = Permission::findOrCreate('صلاحية معلم إضافية', 'web');

        $this->postJson('/api/role/createRole', [
            'name' => 'معلم فرع دمشق',
            'role_family' => RoleFamily::Teacher->value,
            'permissions' => [$extraPermission->id],
        ])->assertCreated();

        $role = Role::where('name', 'معلم فرع دمشق')->firstOrFail();

        foreach ($minimumPermissions as $permissionName) {
            $this->assertTrue(
                $role->hasPermissionTo($permissionName),
                "Missing required Teacher permission: {$permissionName}"
            );
        }

        $this->assertTrue($role->hasPermissionTo($extraPermission));
    }

    public function test_permissions_catalogue_exposes_family_minimums(): void
    {
        $this->authenticateAsRoot();

        $this->getJson('/api/role/getAllPermissions')
            ->assertOk()
            ->assertJsonPath(
                'meta.family_permissions.'.RoleFamily::Teacher->value,
                config('roles.family_permissions.'.RoleFamily::Teacher->value)
            );
    }

    public function test_misleading_name_stays_custom_and_cannot_grant_supervision_by_itself(): void
    {
        $this->authenticateAsRoot();
        $permission = Permission::firstOrCreate([
            'name' => 'صلاحية اختبار غير إشرافية',
            'guard_name' => 'web',
        ]);

        $response = $this->postJson('/api/role/createRole', [
            'name' => 'غير مسؤول',
            'role_family' => RoleFamily::Custom->value,
            'permissions' => [$permission->id],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.role_family', RoleFamily::Custom->value)
            ->assertJsonMissing(['الإشراف على المشاريع والكورسات']);

        $role = Role::where('name', 'غير مسؤول')->firstOrFail();
        $this->assertFalse($role->hasPermissionTo(config('roles.capabilities.supervise')));
    }

    public function test_legacy_suggestion_is_non_authoritative_until_explicitly_reviewed(): void
    {
        $this->authenticateAsRoot();
        $permission = Permission::firstOrCreate([
            'name' => 'صلاحية مراجعة الدور',
            'guard_name' => 'web',
        ]);
        $role = Role::create([
            'name' => 'مسؤول فرع قديم',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Custom->value,
            'is_system' => false,
            'suggested_role_family' => RoleFamily::Supervisor->value,
            'role_family_reviewed_at' => null,
        ]);
        $role->syncPermissions([$permission]);

        $this->getJson("/api/role/getRole/{$role->id}")
            ->assertOk()
            ->assertJsonPath('data.role_family', RoleFamily::Custom->value)
            ->assertJsonPath('data.suggested_role_family', RoleFamily::Supervisor->value)
            ->assertJsonPath('data.needs_role_family_review', true);

        $payload = [
            'name' => $role->name,
            'role_family' => RoleFamily::Supervisor->value,
            'permissions' => [$permission->id],
        ];

        $this->postJson("/api/role/updateRole/{$role->id}", $payload)
            ->assertUnprocessable();

        $this->postJson("/api/role/updateRole/{$role->id}", [
            ...$payload,
            'confirm_privileged_family' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.role_family', RoleFamily::Supervisor->value)
            ->assertJsonPath('data.needs_role_family_review', false);

        $this->assertNotNull($role->fresh()->role_family_reviewed_at);
    }

    public function test_renaming_a_role_does_not_silently_change_its_family(): void
    {
        $this->authenticateAsRoot();
        $permission = Permission::firstOrCreate([
            'name' => 'صلاحية تغيير الاسم',
            'guard_name' => 'web',
        ]);
        $role = app(IRoleService::class)->createRole(
            'منسق فرع',
            RoleFamily::Custom->value,
            [$permission->id]
        );

        $this->postJson("/api/role/updateRole/{$role->id}", [
            'name' => 'مدير فرع بالاسم فقط',
            'permissions' => [$permission->id],
        ])->assertOk();

        $this->assertSame(RoleFamily::Custom, $role->fresh()->role_family);
        $this->assertFalse($role->fresh()->hasPermissionTo(config('roles.capabilities.supervise')));
    }

    private function authenticateAsRoot(): void
    {
        $rootRole = Role::firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'web'],
            [
                'role_family' => RoleFamily::SuperAdmin->value,
                'is_system' => true,
                'role_family_reviewed_at' => now(),
            ]
        );
        $rootUser = User::create([
            'first_name' => 'Root',
            'last_name' => 'User',
            'email' => 'root-role-'.uniqid().'@example.com',
            'phone' => '09'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'birth_date' => '1990-01-01',
            'password' => 'password123',
        ]);
        $rootUser->syncRoles([$rootRole]);
        $this->actingAs($rootUser, 'web');
    }
}
