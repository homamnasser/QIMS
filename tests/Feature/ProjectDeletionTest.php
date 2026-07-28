<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Models\Course;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_linked_to_courses_returns_a_readable_business_error(): void
    {
        $rootUser = $this->createRootUser();
        $project = $this->createProject($rootUser);
        $mosque = Mosque::query()->create(['name' => 'مسجد الاختبار']);

        Course::query()->create([
            'name' => 'كورس مرتبط بالمشروع',
            'description' => 'كورس لاختبار منع حذف المشروع المرتبط.',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'supervisor_id' => $rootUser->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($rootUser, 'web');

        $response = $this->deleteJson("/api/project/deleteProject/{$project->id}");

        $response
            ->assertConflict()
            ->assertJsonPath('code', 409)
            ->assertJsonPath('error_code', 'PROJECT_HAS_COURSES')
            ->assertJsonPath('data.courses_count', 1)
            ->assertJsonPath(
                'message',
                'لا يمكن حذف المشروع لأنه مرتبط بكورس واحد أو أكثر. يمكنك أرشفة المشروع بدلًا من حذفه، أو نقل الكورسات المرتبطة أو حذفها أولًا.'
            );

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('courses', ['project_id' => $project->id]);
    }

    public function test_project_without_courses_can_be_deleted(): void
    {
        $rootUser = $this->createRootUser();
        $project = $this->createProject($rootUser);

        $this->actingAs($rootUser, 'web');

        $this->deleteJson("/api/project/deleteProject/{$project->id}")
            ->assertOk()
            ->assertJsonPath('code', 200);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
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
            'email' => 'project-deletion-root@example.com',
            'phone' => '0999999999',
            'birth_date' => '1990-01-01',
            'password' => 'password123',
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function createProject(User $supervisor): Project
    {
        return Project::query()->create([
            'name' => 'مشروع قابل للحذف',
            'description' => 'مشروع لاختبار سلوك الحذف.',
            'audience' => 'طلاب',
            'supervisor' => $supervisor->id,
        ]);
    }
}
