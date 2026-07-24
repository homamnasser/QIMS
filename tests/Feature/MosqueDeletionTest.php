<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Models\Course;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MosqueDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mosque_linked_to_courses_returns_a_readable_business_error(): void
    {
        $rootUser = $this->createRootUser();
        $mosque = Mosque::query()->create(['name' => 'مسجد الاختبار']);
        $project = Project::query()->create([
            'name' => 'مشروع لاختبار حذف المسجد',
            'description' => 'وصف المشروع',
            'audience' => 'الجميع',
            'supervisor' => $rootUser->id,
        ]);

        Course::query()->create([
            'name' => 'كورس مرتبط بالمسجد',
            'description' => 'كورس لاختبار منع حذف المسجد المرتبط.',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'supervisor_id' => $rootUser->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);

        Sanctum::actingAs($rootUser);

        $response = $this->deleteJson("/api/mosque/deleteMosque/{$mosque->id}");

        $response
            ->assertConflict()
            ->assertJsonPath('code', 409)
            ->assertJsonPath('error_code', 'MOSQUE_HAS_COURSES')
            ->assertJsonPath('data.courses_count', 1)
            ->assertJsonPath(
                'message',
                'لا يمكن حذف المسجد لأنه مرتبط بكورس واحد أو أكثر. يرجى نقل الكورسات المرتبطة بالمسجد أو حذفها أولاً.'
            );

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertDatabaseHas('mosques', ['id' => $mosque->id]);
        $this->assertDatabaseHas('courses', ['mosque_id' => $mosque->id]);
    }

    public function test_mosque_without_courses_can_be_deleted(): void
    {
        $rootUser = $this->createRootUser();
        $mosque = Mosque::query()->create(['name' => 'مسجد بدون كورسات']);

        Sanctum::actingAs($rootUser);

        $this->deleteJson("/api/mosque/deleteMosque/{$mosque->id}")
            ->assertOk()
            ->assertJsonPath('code', 200);

        $this->assertDatabaseMissing('mosques', ['id' => $mosque->id]);
    }

    private function createRootUser(): User
    {
        $role = Role::query()->create([
            'name' => 'super-admin',
            'guard_name' => 'web',
            'role_family' => RoleFamily::SuperAdmin->value,
            'is_system' => true,
            'role_family_reviewed_at' => now(),
        ]);

        $user = User::query()->create([
            'first_name' => 'Root',
            'last_name' => 'User',
            'email' => 'mosque-deletion-root@example.com',
            'phone' => '0999999999',
            'birth_date' => '1990-01-01',
            'password' => 'password123',
        ]);
        $user->syncRoles([$role]);

        return $user;
    }
}
