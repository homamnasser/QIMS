<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Enums\StaffWorkScope;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContactNumberValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Role $staffRole;

    private Role $studentRole;

    private int $studentUsernameSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staffRole = Role::query()->create([
            'name' => 'contact-validation-staff',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Admin->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);
        $this->studentRole = Role::query()->create([
            'name' => 'contact-validation-student',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Student->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);

        foreach (['إنشاء موظف', 'إنشاء طالب', 'تعديل الطالب'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->admin = User::query()->create([
            'first_name' => 'مدير',
            'last_name' => 'الاختبار',
            'email' => 'contact-validation-admin@example.com',
            'phone' => '0900000001',
            'birth_date' => '1990-01-01',
            'password' => 'password123',
            'work_scope' => StaffWorkScope::Institute->value,
        ]);
        $this->admin->syncRoles([$this->staffRole]);
        $this->admin->givePermissionTo(['إنشاء موظف', 'إنشاء طالب', 'تعديل الطالب']);
    }

    public function test_staff_phone_must_have_the_required_format_and_be_unique(): void
    {
        foreach (['0812345678', '091234567', '09123456789', '09abcd1234'] as $index => $phone) {
            $this->actingAs($this->admin)
                ->postJson('/api/createStaffMember', $this->staffPayload([
                    'email' => "invalid-staff-{$index}@example.com",
                    'phone' => $phone,
                ]))
                ->assertUnprocessable()
                ->assertJsonPath(
                    'message.phone.0',
                    'رقم هاتف الموظف يجب أن يبدأ بـ 09 ويتكون من 10 أرقام.'
                );
        }

        $this->actingAs($this->admin)
            ->postJson('/api/createStaffMember', $this->staffPayload())
            ->assertCreated();

        $this->actingAs($this->admin)
            ->postJson('/api/createStaffMember', $this->staffPayload([
                'email' => 'duplicate-staff@example.com',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('message.phone.0', 'رقم الهاتف مستخدم مسبقاً.');
    }

    public function test_student_phone_is_optional_but_formatted_and_unique_when_present(): void
    {
        $missingPhone = $this->studentPayload([
            'first_name' => 'NoPhone',
            'last_name' => 'Student',
        ]);
        unset($missingPhone['phone_number']);

        $createdWithoutPhone = $this->actingAs($this->admin)
            ->postJson('/api/student/createStudent', $missingPhone)
            ->assertCreated();

        $studentId = $createdWithoutPhone->json('data.id');
        $this->assertDatabaseHas('students', [
            'id' => $studentId,
            'phone_number' => null,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/student/updateStudent/{$studentId}", [
                'phone_number' => '',
            ])
            ->assertOk();

        $this->assertDatabaseHas('students', [
            'id' => $studentId,
            'phone_number' => null,
        ]);

        $this->actingAs($this->admin)
            ->postJson('/api/student/createStudent', $this->studentPayload([
                'phone_number' => '0812345678',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath(
                'message.phone_number.0',
                'رقم هاتف الطالب يجب أن يبدأ بـ 09 ويتكون من 10 أرقام.'
            );

        $this->actingAs($this->admin)
            ->postJson('/api/student/createStudent', $this->studentPayload())
            ->assertCreated();

        $this->actingAs($this->admin)
            ->postJson('/api/student/createStudent', $this->studentPayload([
                'first_name' => 'Duplicate',
                'last_name' => 'Student',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath(
                'message.phone_number.0',
                'رقم هاتف الطالب مستخدم مسبقاً.'
            );
    }

    public function test_guardian_phone_must_be_formatted_but_can_be_shared(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/student/createStudent', $this->studentPayload([
                'father_phone' => '0812345678',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath(
                'message.father_phone.0',
                'رقم هاتف الأب أو ولي الأمر يجب أن يبدأ بـ 09 ويتكون من 10 أرقام.'
            );

        $this->actingAs($this->admin)
            ->postJson('/api/student/createStudent', $this->studentPayload())
            ->assertCreated();

        $this->actingAs($this->admin)
            ->postJson('/api/student/createStudent', $this->studentPayload([
                'first_name' => 'Second',
                'last_name' => 'Student',
                'phone_number' => '0955555555',
            ]))
            ->assertCreated();
    }

    private function staffPayload(array $overrides = []): array
    {
        return [
            'first_name' => 'موظف',
            'last_name' => 'جديد',
            'email' => 'new-staff@example.com',
            'phone' => '0912345678',
            'birth_date' => '1995-01-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $this->staffRole->id,
            'work_scope' => StaffWorkScope::Institute->value,
            ...$overrides,
        ];
    }

    private function studentPayload(array $overrides = []): array
    {
        $this->studentUsernameSequence++;

        return [
            'first_name' => 'First',
            'last_name' => 'Student',
            'username' => 'contact-student-'.$this->studentUsernameSequence,
            'phone_number' => '0944444444',
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
