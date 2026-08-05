<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Models\Project;
use App\Models\Role;
use App\Models\Student;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ImageUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Role $staffRole;

    private Role $studentRole;

    private int $studentUsernameSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->staffRole = Role::query()->create([
            'name' => 'image-upload-staff',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Admin->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);
        $this->studentRole = Role::query()->create([
            'name' => 'image-upload-student',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Student->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);

        foreach ([
            'إنشاء موظف',
            'إنشاء طالب',
            'إنشاء مشروع',
            'إنشاء استبيان',
            config('roles.capabilities.supervise'),
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->admin = User::query()->create([
            'first_name' => 'مدير',
            'last_name' => 'الصور',
            'birth_date' => '1990-01-01',
            'phone' => '0991111111',
            'email' => 'image-admin@example.com',
            'password' => 'password123',
        ]);
        $this->admin->givePermissionTo([
            'إنشاء موظف',
            'إنشاء طالب',
            'إنشاء مشروع',
            'إنشاء استبيان',
            config('roles.capabilities.supervise'),
        ]);
    }

    public function test_supported_entities_accept_webp_images_up_to_eight_megabytes(): void
    {
        $this->actingAs($this->admin);

        $staffResponse = $this->post('/api/createStaffMember', [
            ...$this->staffPayload(),
            'image' => $this->webp('staff.webp'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $studentResponse = $this->post('/api/student/createStudent', [
            ...$this->studentPayload(),
            'image' => $this->webp('student.webp'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $projectResponse = $this->post('/api/project/createProject', [
            ...$this->projectPayload(),
            'logo' => $this->webp('project.webp'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $surveyResponse = $this->post('/api/surveys', [
            'name' => 'استبيان الصور',
            'description' => 'استبيان لاختبار رفع الصور.',
            'logo' => $this->webp('survey.webp'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $staff = User::query()->findOrFail($staffResponse->json('data.id'));
        $student = Student::query()->findOrFail($studentResponse->json('data.id'));
        $project = Project::query()->findOrFail($projectResponse->json('data.id'));
        $survey = Survey::query()->findOrFail($surveyResponse->json('data.id'));

        Storage::disk('public')->assertExists($staff->image);
        Storage::disk('public')->assertExists($student->image);
        Storage::disk('public')->assertExists($project->logo);
        Storage::disk('public')->assertExists($survey->logo_path);

        $this->assertNotNull($staffResponse->json('data.image_url'));
        $this->assertNotNull($studentResponse->json('data.image_url'));
        $this->assertNotNull($projectResponse->json('data.logo_url'));
        $this->assertNotNull($surveyResponse->json('data.logo_url'));
    }

    public function test_supported_entities_reject_images_larger_than_eight_megabytes(): void
    {
        $this->actingAs($this->admin);

        $this->assertUploadRejected(
            $this->post('/api/createStaffMember', [
                ...$this->staffPayload(['email' => 'large-staff@example.com']),
                'image' => $this->oversizedPng('large-staff.png'),
            ], ['Accept' => 'application/json']),
            'image',
        );

        $this->assertUploadRejected(
            $this->post('/api/student/createStudent', [
                ...$this->studentPayload([
                    'first_name' => 'Large',
                    'phone_number' => '0941111111',
                ]),
                'image' => $this->oversizedPng('large-student.png'),
            ], ['Accept' => 'application/json']),
            'image',
        );

        $this->assertUploadRejected(
            $this->post('/api/project/createProject', [
                ...$this->projectPayload(['name' => 'مشروع الصورة الكبيرة']),
                'logo' => $this->oversizedPng('large-project.png'),
            ], ['Accept' => 'application/json']),
            'logo',
        );

        $this->assertUploadRejected(
            $this->post('/api/surveys', [
                'name' => 'استبيان الصورة الكبيرة',
                'logo' => $this->oversizedPng('large-survey.png'),
            ], ['Accept' => 'application/json']),
            'logo',
        );
    }

    private function assertUploadRejected(TestResponse $response, string $field): void
    {
        $response->assertUnprocessable();

        $errors = $response->json("errors.{$field}")
            ?? $response->json("message.{$field}");

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('8', $errors[0]);
    }

    private function webp(string $name): File
    {
        return UploadedFile::fake()->image($name, 1200, 800)->size(6144);
    }

    private function oversizedPng(string $name): File
    {
        return UploadedFile::fake()->image($name, 1200, 800)->size(8193);
    }

    private function staffPayload(array $overrides = []): array
    {
        return [
            'first_name' => 'موظف',
            'last_name' => 'الصور',
            'email' => 'image-staff@example.com',
            'phone' => '0931111111',
            'birth_date' => '1995-01-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $this->staffRole->id,
            'work_scope' => 'institute',
            ...$overrides,
        ];
    }

    private function studentPayload(array $overrides = []): array
    {
        $this->studentUsernameSequence++;

        return [
            'first_name' => 'طالب',
            'last_name' => 'الصور',
            'username' => 'image-student-'.$this->studentUsernameSequence,
            'phone_number' => '0951111111',
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_1',
            'father_name' => 'ولي الطالب',
            'parent_social_state' => 'married',
            'father_phone' => '0961111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $this->studentRole->id,
            ...$overrides,
        ];
    }

    private function projectPayload(array $overrides = []): array
    {
        return [
            'name' => 'مشروع الصور',
            'description' => 'مشروع لاختبار رفع الصور.',
            'audience' => 'الطلاب',
            'supervisor' => $this->admin->id,
            ...$overrides,
        ];
    }
}
