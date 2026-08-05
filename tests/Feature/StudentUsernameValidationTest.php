<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Enums\StaffWorkScope;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StudentUsernameValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Role $studentRole;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::query()->create([
            'name' => 'student-username-admin',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Admin->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);
        $this->studentRole = Role::query()->create([
            'name' => 'student-username-student',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Student->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);

        foreach (['إنشاء طالب', 'تعديل الطالب'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->admin = User::query()->create([
            'first_name' => 'مدير',
            'last_name' => 'أسماء المستخدمين',
            'email' => 'student-username-admin@example.com',
            'phone' => '0900000011',
            'birth_date' => '1990-01-01',
            'password' => 'password123',
            'work_scope' => StaffWorkScope::Institute->value,
        ]);
        $this->admin->syncRoles([$adminRole]);
        $this->admin->givePermissionTo(['إنشاء طالب', 'تعديل الطالب']);
    }

    public function test_username_is_required_and_rejects_non_standard_values(): void
    {
        $missing = $this->studentPayload();
        unset($missing['username']);

        $this->actingAs($this->admin)
            ->postJson('/api/student/createStudent', $missing)
            ->assertUnprocessable()
            ->assertJsonPath('message.username.0', 'اسم المستخدم مطلوب.');

        $invalidUsernames = [
            'ab',
            str_repeat('a', 31),
            'طالب-جديد',
            ' student-name',
            'student-name ',
            'student name',
            '.student-name',
            'student-name_',
            'student..name',
            'student@name',
        ];

        foreach ($invalidUsernames as $username) {
            $this->actingAs($this->admin)
                ->postJson('/api/student/createStudent', $this->studentPayload([
                    'username' => $username,
                ]))
                ->assertUnprocessable()
                ->assertJsonPath('message.username.0', fn ($message) => is_string($message));
        }
    }

    public function test_username_is_canonical_unique_and_independent_of_display_names(): void
    {
        $created = $this->actingAs($this->admin)
            ->postJson('/api/student/createStudent', $this->studentPayload([
                'first_name' => 'اسم',
                'last_name' => 'للعرض',
                'username' => 'Student.Name-1_test',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.username', 'student.name-1_test');

        $studentId = $created->json('data.id');

        $this->actingAs($this->admin)
            ->postJson('/api/student/createStudent', $this->studentPayload([
                'username' => 'STUDENT.NAME-1_TEST',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('message.username.0', 'اسم المستخدم مستخدم مسبقاً.');

        $this->actingAs($this->admin)
            ->postJson("/api/student/updateStudent/{$studentId}", [
                'first_name' => 'اسم جديد',
                'last_name' => 'للعرض فقط',
            ])
            ->assertOk()
            ->assertJsonPath('data.username', 'student.name-1_test');

        $this->actingAs($this->admin)
            ->postJson("/api/student/updateStudent/{$studentId}", [
                'username' => 'STUDENT.NAME-1_TEST',
            ])
            ->assertOk()
            ->assertJsonPath('data.username', 'student.name-1_test');

        $other = $this->actingAs($this->admin)
            ->postJson('/api/student/createStudent', $this->studentPayload([
                'username' => 'other.student-2',
            ]))
            ->assertCreated();

        $this->actingAs($this->admin)
            ->postJson("/api/student/updateStudent/{$studentId}", [
                'username' => $other->json('data.username'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message.username.0', 'اسم المستخدم مستخدم مسبقاً.');

        $this->actingAs($this->admin)
            ->postJson("/api/student/updateStudent/{$studentId}", [
                'username' => 'renamed.student-3',
            ])
            ->assertOk()
            ->assertJsonPath('data.username', 'renamed.student-3');

        $this->assertDatabaseHas('students', [
            'id' => $studentId,
            'first_name' => 'اسم جديد',
            'last_name' => 'للعرض فقط',
            'username' => 'renamed.student-3',
        ]);
    }

    public function test_student_login_uses_only_the_explicit_username(): void
    {
        $created = $this->actingAs($this->admin)
            ->postJson('/api/student/createStudent', $this->studentPayload([
                'first_name' => 'Display',
                'last_name' => 'Name',
                'username' => 'chosen.login-2',
            ]))
            ->assertCreated();

        $student = Student::query()->findOrFail($created->json('data.id'));

        $this->postJson('/api/mobile/auth/login', [
            'login' => 'CHOSEN.LOGIN-2',
            'password' => 'password123',
            'device_name' => 'Username Test Device',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $student->id)
            ->assertJsonPath('data.user.username', 'chosen.login-2');

        foreach (['display-name', 'display.name'] as $generatedFromNames) {
            $this->postJson('/api/mobile/auth/login', [
                'login' => $generatedFromNames,
                'password' => 'password123',
                'device_name' => 'Username Test Device',
            ])
                ->assertUnauthorized()
                ->assertJsonPath('error_code', 'AUTHENTICATION_FAILED');
        }
    }

    private function studentPayload(array $overrides = []): array
    {
        return [
            'first_name' => 'طالب',
            'last_name' => 'اختبار',
            'username' => 'student-'.fake()->unique()->numberBetween(1000, 999999),
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_1',
            'father_name' => 'ولي الطالب',
            'parent_social_state' => 'married',
            'father_phone' => '0933333333',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $this->studentRole->id,
            ...$overrides,
        ];
    }
}
