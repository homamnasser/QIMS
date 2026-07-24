<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Course;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\Student;
use App\Models\StudentCircle;
use App\Models\User;
use App\Services\StudentCircleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCircleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_join_different_courses_in_one_mosque_but_not_the_same_course_or_another_mosque(): void
    {
        $supervisor = User::create([
            'first_name' => 'أحمد',
            'last_name' => 'المشرف',
            'birth_date' => '1990-01-01',
            'phone' => '0999999999',
            'email' => 'student-circle-supervisor@example.com',
            'password' => 'password123',
        ]);

        $project = Project::create([
            'name' => 'مشروع الاختبار',
            'description' => 'مشروع لاختبار إلحاق الطلاب بالحلقات',
            'audience' => 'الطلاب',
            'supervisor' => $supervisor->id,
        ]);

        $firstMosque = Mosque::create(['name' => 'المسجد الأول', 'mosque_code' => 'A']);
        $secondMosque = Mosque::create(['name' => 'المسجد الثاني', 'mosque_code' => 'B']);

        $firstCourse = $this->createCourse('الكورس الأول', $firstMosque->id, $project->id, $supervisor->id);
        $secondCourse = $this->createCourse('الكورس الثاني', $firstMosque->id, $project->id, $supervisor->id);
        $otherMosqueCourse = $this->createCourse('كورس المسجد الثاني', $secondMosque->id, $project->id, $supervisor->id);

        $originalCircle = Circle::create([
            'name' => 'الحلقة الأصلية',
            'teacher_id' => $supervisor->id,
            'course_id' => $firstCourse->id,
        ]);
        $sameCourseCircle = Circle::create([
            'name' => 'حلقة أخرى للكورس نفسه',
            'teacher_id' => $supervisor->id,
            'course_id' => $firstCourse->id,
        ]);
        $differentCourseCircle = Circle::create([
            'name' => 'حلقة الكورس الثاني',
            'teacher_id' => $supervisor->id,
            'course_id' => $secondCourse->id,
        ]);
        $otherMosqueCircle = Circle::create([
            'name' => 'حلقة المسجد الثاني',
            'teacher_id' => $supervisor->id,
            'course_id' => $otherMosqueCourse->id,
        ]);

        $student = Student::create([
            'first_name' => 'محمد',
            'last_name' => 'الطالب',
            'username' => 'student-circle-test',
            'birth_date' => '2012-01-01',
            'academic_class' => 'السادس',
            'reading_level' => 'level_2',
            'father_name' => 'خالد',
            'parent_social_state' => 'married',
            'father_phone' => '0988888888',
            'password' => 'password123',
        ]);

        StudentCircle::create([
            'student' => $student->id,
            'circle' => $originalCircle->id,
        ]);

        $service = app(StudentCircleService::class);

        $sameCourseResult = $service->addStudentsToCircle($sameCourseCircle->id, [$student->id]);

        $this->assertTrue($sameCourseResult['students']->isEmpty());
        $this->assertStringContainsString('أكثر من حلقة للكورس نفسه', $sameCourseResult['conflicts'][0]);
        $this->assertDatabaseMissing('student_circles', [
            'student' => $student->id,
            'circle' => $sameCourseCircle->id,
        ]);

        $differentCourseResult = $service->addStudentsToCircle($differentCourseCircle->id, [$student->id]);

        $this->assertCount(1, $differentCourseResult['students']);
        $this->assertEmpty($differentCourseResult['conflicts']);
        $this->assertSame('A-000001', $student->fresh()->selfnumber);
        $this->assertSame($firstMosque->id, $student->fresh()->mosque_id);
        $this->assertDatabaseHas('student_circles', [
            'student' => $student->id,
            'circle' => $differentCourseCircle->id,
        ]);

        $otherMosqueResult = $service->addStudentsToCircle($otherMosqueCircle->id, [$student->id]);

        $this->assertTrue($otherMosqueResult['students']->isEmpty());
        $this->assertStringContainsString('لا يمكن تسجيله في مساجد مختلفة', $otherMosqueResult['conflicts'][0]);
        $this->assertDatabaseMissing('student_circles', [
            'student' => $student->id,
            'circle' => $otherMosqueCircle->id,
        ]);
    }

    private function createCourse(string $name, int $mosqueId, int $projectId, int $supervisorId): Course
    {
        return Course::create([
            'name' => $name,
            'description' => 'وصف الكورس',
            'mosque_id' => $mosqueId,
            'project_id' => $projectId,
            'supervisor_id' => $supervisorId,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);
    }
}
