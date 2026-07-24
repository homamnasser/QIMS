<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Course;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\Student;
use App\Models\StudentSelfNumberReservation;
use App\Models\User;
use App\Services\CircleService;
use App\Services\CourseService;
use App\Services\StudentCircleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSelfNumberLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_circle_removal_deactivates_the_number_without_reusing_its_sequence(): void
    {
        [$mosque, $firstCircle, $secondCircle] = $this->createEnrollmentContext();
        $student = $this->createStudent('selfnumber-first');
        $service = app(StudentCircleService::class);

        $firstEnrollment = $service->addStudentsToCircle($firstCircle->id, [$student->id]);

        $this->assertCount(1, $firstEnrollment['students']);
        $this->assertSame('O-000001', $student->fresh()->selfnumber);
        $this->assertDatabaseHas('student_selfnumber_reservations', [
            'selfnumber' => 'O-000001',
            'student_id' => $student->id,
            'mosque_id' => $mosque->id,
            'deactivated_at' => null,
        ]);

        $service->addStudentsToCircle($secondCircle->id, [$student->id]);
        $service->removeStudentFromCircle($secondCircle->id, $student->id);

        $this->assertSame('O-000001', $student->fresh()->selfnumber);
        $this->assertSame($mosque->id, $student->fresh()->mosque_id);

        $service->removeStudentFromCircle($firstCircle->id, $student->id);

        $this->assertNull($student->fresh()->selfnumber);
        $this->assertNull($student->fresh()->mosque_id);
        $this->assertNotNull(
            StudentSelfNumberReservation::where('selfnumber', 'O-000001')->firstOrFail()->deactivated_at,
        );

        $nextStudent = $this->createStudent('selfnumber-next');
        $service->addStudentsToCircle($firstCircle->id, [$nextStudent->id]);

        $this->assertSame('O-000002', $nextStudent->fresh()->selfnumber);

        $service->addStudentsToCircle($secondCircle->id, [$student->id]);

        $this->assertSame('O-000003', $student->fresh()->selfnumber);
        $this->assertSame(3, $mosque->fresh()->next_student_sequence);
        $this->assertSame(3, StudentSelfNumberReservation::count());
        $this->assertDatabaseHas('student_selfnumber_reservations', [
            'selfnumber' => 'O-000003',
            'student_id' => $student->id,
            'deactivated_at' => null,
        ]);
    }

    public function test_circle_and_course_deletion_deactivate_numbers_only_after_all_enrollments_are_gone(): void
    {
        [$mosque, $firstCircle, $secondCircle, $firstCourse, $secondCourse] = $this->createEnrollmentContext();
        $firstStudent = $this->createStudent('selfnumber-circle-delete');
        $secondStudent = $this->createStudent('selfnumber-course-delete');
        $enrollmentService = app(StudentCircleService::class);

        $enrollmentService->addStudentsToCircle($firstCircle->id, [$firstStudent->id]);
        $enrollmentService->addStudentsToCircle($secondCircle->id, [$secondStudent->id]);

        app(CircleService::class)->deleteCircle($firstCircle);

        $this->assertNull($firstStudent->fresh()->selfnumber);
        $this->assertSame('O-000002', $secondStudent->fresh()->selfnumber);

        app(CourseService::class)->deleteCourse($secondCourse);

        $this->assertNull($secondStudent->fresh()->selfnumber);

        $replacementCircle = Circle::create([
            'name' => 'الحلقة البديلة',
            'teacher_id' => $firstCourse->supervisor_id,
            'course_id' => $firstCourse->id,
        ]);
        $thirdStudent = $this->createStudent('selfnumber-after-deletions');
        $enrollmentService->addStudentsToCircle($replacementCircle->id, [$thirdStudent->id]);

        $this->assertSame('O-000003', $thirdStudent->fresh()->selfnumber);
        $this->assertSame(3, $mosque->fresh()->next_student_sequence);
        $this->assertNotNull(
            StudentSelfNumberReservation::where('selfnumber', 'O-000001')->firstOrFail()->deactivated_at,
        );
        $this->assertNotNull(
            StudentSelfNumberReservation::where('selfnumber', 'O-000002')->firstOrFail()->deactivated_at,
        );
    }

    /**
     * @return array{Mosque, Circle, Circle, Course, Course}
     */
    private function createEnrollmentContext(): array
    {
        $supervisor = User::create([
            'first_name' => 'أحمد',
            'last_name' => 'المشرف',
            'birth_date' => '1990-01-01',
            'phone' => '0999999911',
            'email' => 'selfnumber-supervisor@example.com',
            'password' => 'password123',
        ]);
        $project = Project::create([
            'name' => 'مشروع الرقم الذاتي',
            'description' => 'مشروع لاختبار دورة حياة الرقم الذاتي.',
            'audience' => 'الطلاب',
            'supervisor' => $supervisor->id,
        ]);
        $mosque = Mosque::create([
            'name' => 'مسجد الأنصار',
            'mosque_code' => 'O',
        ]);
        $firstCourse = $this->createCourse('الكورس الأول', $mosque, $project, $supervisor);
        $secondCourse = $this->createCourse('الكورس الثاني', $mosque, $project, $supervisor);
        $firstCircle = Circle::create([
            'name' => 'الحلقة الأولى',
            'teacher_id' => $supervisor->id,
            'course_id' => $firstCourse->id,
        ]);
        $secondCircle = Circle::create([
            'name' => 'الحلقة الثانية',
            'teacher_id' => $supervisor->id,
            'course_id' => $secondCourse->id,
        ]);

        return [$mosque, $firstCircle, $secondCircle, $firstCourse, $secondCourse];
    }

    private function createCourse(string $name, Mosque $mosque, Project $project, User $supervisor): Course
    {
        return Course::create([
            'name' => $name,
            'description' => 'وصف الكورس التجريبي.',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'supervisor_id' => $supervisor->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);
    }

    private function createStudent(string $username): Student
    {
        return Student::create([
            'first_name' => 'محمد',
            'last_name' => $username,
            'username' => $username,
            'birth_date' => '2012-01-01',
            'academic_class' => 'السادس',
            'reading_level' => 'level_2',
            'father_name' => 'خالد',
            'parent_social_state' => 'married',
            'father_phone' => '0988888811',
            'password' => 'password123',
        ]);
    }
}
