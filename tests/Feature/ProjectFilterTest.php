<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $supervisor1;

    protected User $supervisor2;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'عرض كافة المشاريع', 'guard_name' => 'web']);

        $this->admin = User::query()->create([
            'first_name' => 'المدير',
            'last_name' => 'الأعلى',
            'email' => 'admin@example.com',
            'phone' => '0911111111',
            'birth_date' => '1990-01-01',
            'password' => 'password123',
        ]);
        $this->admin->givePermissionTo('عرض كافة المشاريع');

        $this->supervisor1 = User::query()->create([
            'first_name' => 'أحمد',
            'last_name' => 'المشرف',
            'email' => 'sup1@example.com',
            'phone' => '0922222222',
            'birth_date' => '1992-01-01',
            'password' => 'password123',
        ]);

        $this->supervisor2 = User::query()->create([
            'first_name' => 'محمود',
            'last_name' => 'المشرف',
            'email' => 'sup2@example.com',
            'phone' => '0933333333',
            'birth_date' => '1994-01-01',
            'password' => 'password123',
        ]);

        Project::create([
            'name' => 'مشروع حفظ القرآن',
            'description' => 'وصف حفظ القرآن',
            'audience' => 'الطلاب الجدد',
            'supervisor' => $this->supervisor1->id,
            'is_active' => true,
        ]);

        Project::create([
            'name' => 'مشروع إتقان التجويد',
            'description' => 'وصف التجويد',
            'audience' => 'الطلاب المتقدمون',
            'supervisor' => $this->supervisor2->id,
            'is_active' => true,
        ]);

        Project::create([
            'name' => 'مشروع مراجعة الأجزاء',
            'description' => 'وصف المراجعة',
            'audience' => 'الطلاب الجدد',
            'supervisor' => $this->supervisor2->id,
            'is_active' => false,
        ]);
    }

    public function test_can_search_projects_by_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/project/getAllProjects?name=تجويد');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'مشروع إتقان التجويد');
    }

    public function test_can_filter_projects_by_supervisor(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/project/getAllProjects?supervisor='.$this->supervisor2->id);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_projects_by_audience(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/project/getAllProjects?audience='.urlencode('الطلاب الجدد'));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_combine_name_supervisor_and_audience_filters(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/project/getAllProjects?supervisor='.$this->supervisor2->id.'&audience='.urlencode('الطلاب الجدد'));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'مشروع مراجعة الأجزاء');
    }

    public function test_can_fetch_distinct_target_audiences(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/project/getAudiences');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['الطلاب الجدد'])
            ->assertJsonFragment(['الطلاب المتقدمون']);
    }
}
