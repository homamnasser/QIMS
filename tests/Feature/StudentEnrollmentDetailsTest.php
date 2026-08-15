<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Http\Resources\StudentResource;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\Role;
use App\Models\StudentCircle;
use App\Models\User;
use App\Services\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentEnrollmentDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_details_include_distinct_circles_courses_and_mosque(): void
    {
        $studentRole = Role::create([
            'name' => 'student',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Student->value,
            'is_system' => true,
        ]);

        $student = app(StudentService::class)->createStudent([
            'first_name' => 'همام',
            'last_name' => 'ناصر',
            'username' => 'homam-nasser',
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_2',
            'father_name' => 'ناصر',
            'parent_social_state' => 'married',
            'father_phone' => '0982244990',
            'password' => 'password123',
            'role_id' => $studentRole->id,
        ]);

        $supervisor = User::create([
            'first_name' => 'أحمد',
            'last_name' => 'المشرف',
            'email' => 'student-details-supervisor@example.com',
            'phone' => '0999999901',
            'birth_date' => '1990-01-01',
            'password' => 'password123',
        ]);

        $teacher = User::create([
            'first_name' => 'محمود',
            'last_name' => 'المعلم',
            'email' => 'student-details-teacher@example.com',
            'phone' => '0999999902',
            'birth_date' => '1991-01-01',
            'password' => 'password123',
        ]);

        $mosque = Mosque::create(['name' => 'مسجد الأنصار']);
        $project = Project::create([
            'name' => 'مشروع التحفيظ',
            'description' => 'مشروع مخصص لاختبار تفاصيل انتساب الطالب.',
            'audience' => 'الطلاب',
            'supervisor' => $supervisor->id,
            'is_active' => true,
        ]);

        $firstCourse = Course::create([
            'name' => 'القرآن الكريم',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'supervisor_id' => $supervisor->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        $secondCourse = Course::create([
            'name' => 'التجويد',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'supervisor_id' => $supervisor->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        $firstCircle = Circle::create([
            'name' => 'حلقة الهدى',
            'teacher_id' => $teacher->id,
            'course_id' => $firstCourse->id,
        ]);

        $secondCircle = Circle::create([
            'name' => 'حلقة النور',
            'teacher_id' => $teacher->id,
            'course_id' => $secondCourse->id,
        ]);

        StudentCircle::create([
            'student' => $student->id,
            'circle' => $firstCircle->id,
        ]);
        StudentCircle::create([
            'student' => $student->id,
            'circle' => $secondCircle->id,
        ]);

        $studentDetails = app(StudentService::class)->getStudentById($student->id);
        $resource = (new StudentResource($studentDetails))->resolve();

        $this->assertSame(
            ['حلقة الهدى', 'حلقة النور'],
            array_column($resource['enrollment']['circles'], 'name')
        );
        $this->assertSame(
            ['القرآن الكريم', 'التجويد'],
            array_column($resource['enrollment']['courses'], 'name')
        );
        $this->assertSame(
            ['مسجد الأنصار'],
            array_column($resource['enrollment']['mosques'], 'name')
        );

        // تاريخ الميلاد يخرج يوماً مقروءاً لا لحظة زمنية بصيغة ISO.
        $this->assertSame('2012-01-01', $resource['birth_date']);
    }
}
