<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Course;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\ReadingImprovement;
use App\Models\Student;
use App\Models\StudentCircle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * مسار تقييم التحسن في القراءة.
 *
 * المتحكّم كان مكتملاً بلا مسار ولا صلاحية ولا ربط للخدمة، فكان معيار القراءة
 * يعود `missing` دائماً — وهو المعيار الوحيد الذي يمنع الدورة من الوصول إلى
 * «جاهزة» بلا أي طريق لعلاجه من الواجهة.
 */
class ReadingImprovementApiTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private Student $student;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $mosque = Mosque::create(['name' => 'مسجد القراءة', 'mosque_code' => 'RD1']);

        $this->staff = User::create([
            'first_name' => 'موظف',
            'last_name' => 'القراءة',
            'email' => 'reading-staff@example.com',
            'phone' => '0991000020',
            'birth_date' => '1990-01-01',
            'password' => 'password123',
        ]);

        $project = Project::create([
            'name' => 'مشروع القراءة',
            'description' => 'اختبار تقييم القراءة.',
            'audience' => 'الطلاب',
            'supervisor' => $this->staff->id,
            'is_active' => true,
        ]);
        $this->course = Course::create([
            'name' => 'مقرر القراءة',
            'description' => 'مقرر',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'supervisor_id' => $this->staff->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_active' => true,
        ]);
        $circle = Circle::create([
            'name' => 'حلقة القراءة',
            'teacher_id' => $this->staff->id,
            'course_id' => $this->course->id,
            'quran_mode' => 'none',
        ]);

        $this->student = Student::create([
            'mosque_id' => $mosque->id,
            'first_name' => 'طالب',
            'last_name' => 'القراءة',
            'username' => 'reading-student',
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_2',
            'father_name' => 'ولي الأمر',
            'parent_social_state' => 'married',
            'father_phone' => '0980000020',
            'password' => 'student123',
        ]);
        StudentCircle::create([
            'student' => $this->student->id,
            'circle' => $circle->id,
        ]);
    }

    public function test_reading_assessment_can_be_created_and_corrected_through_the_api(): void
    {
        $this->grant(['عرض كافة تقييمات القراءة', 'إنشاء تقييم قراءة', 'تعديل تقييم القراءة']);

        $created = $this->actingAs($this->staff)
            ->postJson('/api/reading-improvement/createReadingImprovement', [
                'student' => $this->student->id,
                'course' => $this->course->id,
                'type' => 'significant_improvement',
                'description' => 'تحسّن واضح بين القياسين.',
                'occurred_at' => '2026-01-20',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('significant_improvement', $created['type']);
        $this->assertSame('2026-01-20', $created['occurred_at']);

        // سجل واحد لكل (طالب، كورس): التصحيح يحدّثه ولا يكدّس سجلاً يتجاهله المعيار.
        $this->actingAs($this->staff)
            ->postJson('/api/reading-improvement/updateReadingImprovement/'.$created['id'], [
                'type' => 'decline',
                'occurred_at' => '2026-01-20',
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'decline');

        $this->assertSame(1, ReadingImprovement::query()->count());

        $listed = $this->actingAs($this->staff)
            ->getJson('/api/reading-improvement/getAllReadingImprovements?course_id='.$this->course->id)
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $listed);
        $this->assertSame($this->student->id, $listed[0]['student_id']);
    }

    public function test_reading_routes_are_closed_without_their_permissions(): void
    {
        $this->grant(['عرض كافة تقييمات القراءة']);

        $this->actingAs($this->staff)
            ->getJson('/api/reading-improvement/getAllReadingImprovements')
            ->assertOk();

        $this->actingAs($this->staff)
            ->postJson('/api/reading-improvement/createReadingImprovement', [
                'student' => $this->student->id,
                'course' => $this->course->id,
                'type' => 'slight_improvement',
            ])
            ->assertForbidden();
    }

    public function test_assessment_is_rejected_for_a_student_outside_the_course(): void
    {
        $this->grant(['إنشاء تقييم قراءة']);

        $outsider = Student::create([
            'mosque_id' => $this->student->mosque_id,
            'first_name' => 'طالب',
            'last_name' => 'خارج المقرر',
            'username' => 'outsider-student',
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_1',
            'father_name' => 'ولي الأمر',
            'parent_social_state' => 'married',
            'father_phone' => '0980000021',
            'password' => 'student123',
        ]);

        $this->actingAs($this->staff)
            ->postJson('/api/reading-improvement/createReadingImprovement', [
                'student' => $outsider->id,
                'course' => $this->course->id,
                'type' => 'slight_improvement',
            ])
            ->assertStatus(422);
    }

    /** @param  string[]  $permissions */
    private function grant(array $permissions): void
    {
        $role = Role::firstOrCreate(['name' => 'reading-tester-'.count($permissions), 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->staff->syncRoles([$role]);
    }
}
