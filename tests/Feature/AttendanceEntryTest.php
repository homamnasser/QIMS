<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\Student;
use App\Models\StudentCircle;
use App\Models\StudentCourseAbsence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * مسار تسجيل الحضور الذي تستهلكه مساحة عمل الحلقة.
 *
 * الحضور يُسجَّل جلسةً جلسة لحلقة كاملة، فعمود الحالة يحتاج **قراءة جلسة واحدة**
 * لا كل تواريخ الكورس. وما يُكتب هنا يقرأه `AttendanceCalculator` مباشرة، فربط
 * السجل بجلسته وحفظ العذر ليسا تفصيلاً تجميلياً: أولهما مسار المطابقة المفضّل
 * لدى المعيار، والثاني يفرّق بين وزن كامل ونصف وزن في درجة الطالب.
 */
class AttendanceEntryTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private Student $student;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $mosque = Mosque::create(['name' => 'مسجد الحضور', 'mosque_code' => 'ATT01']);

        $this->staff = User::create([
            'first_name' => 'موظف',
            'last_name' => 'الحضور',
            'email' => 'attendance-staff@example.com',
            'phone' => '0991000010',
            'birth_date' => '1990-01-01',
            'password' => 'password123',
        ]);

        $role = Role::create(['name' => 'attendance-tester', 'guard_name' => 'web']);
        foreach (['عرض كافة الغيابات', 'إنشاء غياب', 'تعديل الغياب'] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->staff->syncRoles([$role]);

        $project = Project::create([
            'name' => 'مشروع الحضور',
            'description' => 'اختبار تسجيل الحضور.',
            'audience' => 'الطلاب',
            'supervisor' => $this->staff->id,
            'is_active' => true,
        ]);
        $this->course = Course::create([
            'name' => 'مقرر الحضور',
            'description' => 'مقرر',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'supervisor_id' => $this->staff->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_active' => true,
        ]);
        $circle = Circle::create([
            'name' => 'حلقة الحضور',
            'teacher_id' => $this->staff->id,
            'course_id' => $this->course->id,
            'quran_mode' => 'none',
        ]);

        $this->student = Student::create([
            'mosque_id' => $mosque->id,
            'first_name' => 'طالب',
            'last_name' => 'الحضور',
            'username' => 'attendance-student',
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_2',
            'father_name' => 'ولي الأمر',
            'parent_social_state' => 'married',
            'father_phone' => '0980000001',
            'password' => 'student123',
        ]);
        StudentCircle::create([
            'student' => $this->student->id,
            'circle' => $circle->id,
        ]);

        foreach (['2026-01-05', '2026-01-07'] as $date) {
            CourseDate::create([
                'course_id' => $this->course->id,
                'session_date' => $date,
            ]);
        }
    }

    public function test_recorded_absence_is_linked_to_its_session_and_keeps_the_excuse(): void
    {
        $this->actingAs($this->staff)
            ->postJson('/api/absence/createAbsence', [
                'student' => $this->student->id,
                'course' => $this->course->id,
                'type' => 'full',
                'date' => '2026-01-05',
                'is_excused' => true,
                'note' => 'عذر مرضي',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_excused', true);

        $session = CourseDate::where('session_date', '2026-01-05')->firstOrFail();
        $absence = StudentCourseAbsence::firstOrFail();

        // المعيار يطابق على course_date_id أولاً ولا يرجع إلى التاريخ إلا للسجلات
        // القديمة؛ الاشتقاق من (الكورس، التاريخ) يجعل كل سجل جديد مرتبطاً مباشرة.
        $this->assertSame($session->id, $absence->course_date_id);
        $this->assertTrue($absence->is_excused);
    }

    public function test_updating_an_absence_moves_its_session_link_with_the_date(): void
    {
        $absence = StudentCourseAbsence::create([
            'student' => $this->student->id,
            'course' => $this->course->id,
            'type' => 'full',
            'date' => '2026-01-05',
            'course_date_id' => CourseDate::where('session_date', '2026-01-05')->value('id'),
        ]);

        $this->actingAs($this->staff)
            ->postJson('/api/absence/updateAbsence/'.$absence->id, [
                'type' => 'present',
                'date' => '2026-01-07',
            ])
            ->assertOk();

        $this->assertSame(
            CourseDate::where('session_date', '2026-01-07')->value('id'),
            $absence->fresh()->course_date_id,
            'سجل نُقل إلى جلسة أخرى وبقي مرتبطاً بالجلسة القديمة يُحتسب في اليوم الخطأ.'
        );
    }

    public function test_absences_can_be_read_one_session_at_a_time_and_unfiltered_as_before(): void
    {
        foreach (['2026-01-05', '2026-01-07'] as $date) {
            StudentCourseAbsence::create([
                'student' => $this->student->id,
                'course' => $this->course->id,
                'type' => 'present',
                'date' => $date,
            ]);
        }

        $session = $this->actingAs($this->staff)
            ->getJson('/api/absence/getAllAbsences?course_id='.$this->course->id.'&date=2026-01-05')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $session);
        $this->assertSame('2026-01-05', $session[0]['date']);

        // تراجعية: بلا الفلتر الجديد يبقى الرد كما كان حرفياً.
        $all = $this->actingAs($this->staff)
            ->getJson('/api/absence/getAllAbsences?course_id='.$this->course->id)
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $all);
    }
}
