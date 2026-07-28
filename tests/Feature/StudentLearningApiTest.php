<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Models\Circle;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Memorization;
use App\Models\Mosque;
use App\Models\Note;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Sabr;
use App\Models\Student;
use App\Models\StudentCircle;
use App\Models\Subject;
use App\Models\User;
use App\Models\Warning;
use Database\Seeders\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentLearningApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_migration_grants_only_student_family_roles_the_new_capabilities(): void
    {
        $studentRole = $this->createRole('student-api-role', RoleFamily::Student);
        $teacherRole = $this->createRole('teacher-api-role', RoleFamily::Teacher);
        $adminRole = $this->createRole('admin-api-role', RoleFamily::Admin);
        $existingPermission = Permission::firstOrCreate([
            'name' => 'صلاحية موجودة مسبقاً',
            'guard_name' => 'web',
        ]);
        $teacherRole->givePermissionTo($existingPermission);

        $migration = require database_path(
            'migrations/2026_07_27_000000_add_student_self_service_permissions.php'
        );
        $migration->up();

        foreach (config('roles.student_capabilities') as $permission) {
            $this->assertTrue($studentRole->fresh()->hasPermissionTo($permission));
            $this->assertFalse($teacherRole->fresh()->hasPermissionTo($permission));
            $this->assertFalse($adminRole->fresh()->hasPermissionTo($permission));
        }

        $this->assertTrue($teacherRole->fresh()->hasPermissionTo($existingPermission));
    }

    public function test_local_test_data_assigns_student_self_service_permissions_only_to_student_role(): void
    {
        $this->seed(TestDataSeeder::class);

        $studentRole = Role::where('name', 'student')->firstOrFail();
        $otherRoles = Role::whereIn('name', [
            'super-admin',
            'admin',
            'supervisor',
            'teacher',
        ])->get();

        foreach (config('roles.student_capabilities') as $permission) {
            $this->assertTrue($studentRole->hasPermissionTo($permission));

            foreach ($otherRoles as $role) {
                $this->assertFalse($role->hasPermissionTo($permission));
            }
        }

        foreach (config('roles.legacy_student_capabilities') as $permission) {
            $this->assertFalse($studentRole->hasPermissionTo($permission));
        }

        $teacherRole = Role::where('name', 'teacher')->firstOrFail();
        foreach (config('roles.legacy_student_capabilities') as $permission) {
            $this->assertTrue($teacherRole->hasPermissionTo($permission));
        }
    }

    public function test_follow_up_migration_replaces_only_student_legacy_record_permissions(): void
    {
        $studentRole = $this->createRole('student-legacy-role', RoleFamily::Student);
        $teacherRole = $this->createRole('teacher-legacy-role', RoleFamily::Teacher);
        $legacyPermissions = collect(config('roles.legacy_student_capabilities'))
            ->map(fn (string $name): Permission => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));
        $studentRole->givePermissionTo($legacyPermissions);
        $teacherRole->givePermissionTo($legacyPermissions);

        $migration = require database_path(
            'migrations/2026_07_27_000001_separate_student_record_permissions.php'
        );
        $migration->up();

        $recordPermissions = collect(config('roles.student_capabilities'))
            ->only(['notes', 'sabrs', 'memorizations', 'warnings', 'exams']);

        foreach ($recordPermissions as $permission) {
            $this->assertTrue($studentRole->fresh()->hasPermissionTo($permission));
            $this->assertFalse($teacherRole->fresh()->hasPermissionTo($permission));
        }

        foreach ($legacyPermissions as $permission) {
            $this->assertFalse($studentRole->fresh()->hasPermissionTo($permission));
            $this->assertTrue($teacherRole->fresh()->hasPermissionTo($permission));
        }
    }

    public function test_student_endpoints_return_only_the_authenticated_students_learning_records(): void
    {
        $studentRole = $this->createRole('student', RoleFamily::Student);
        $studentRole->givePermissionTo(array_values(config('roles.student_capabilities')));

        $teacher = $this->createUser('student-api-teacher@example.com', '0990000001');
        $supervisor = $this->createUser('student-api-supervisor@example.com', '0990000002');
        $ownMosque = Mosque::create([
            'name' => 'مسجد الطالب',
            'mosque_code' => 'STUDENT01',
        ]);
        $otherMosque = Mosque::create([
            'name' => 'مسجد طالب آخر',
            'mosque_code' => 'STUDENT02',
        ]);
        $project = Project::create([
            'name' => 'مشروع واجهات الطالب',
            'description' => 'مشروع لاختبار حصر بيانات الطالب.',
            'audience' => 'الطلاب',
            'supervisor' => $supervisor->id,
            'is_active' => true,
        ]);

        $ownCourse = $this->createCourse(
            'كورس الطالب',
            $ownMosque,
            $project,
            $supervisor
        );
        $sameMosqueOtherCourse = $this->createCourse(
            'كورس طالب آخر في المسجد نفسه',
            $ownMosque,
            $project,
            $supervisor
        );
        $otherMosqueCourse = $this->createCourse(
            'كورس مسجد آخر',
            $otherMosque,
            $project,
            $supervisor
        );

        $ownCircle = $this->createCircle('حلقة الطالب', $teacher, $ownCourse);
        $sameMosqueOtherCircle = $this->createCircle(
            'حلقة طالب آخر',
            $teacher,
            $sameMosqueOtherCourse
        );
        $outsideMosqueCircle = $this->createCircle(
            'حلقة خارج مسجد الطالب',
            $teacher,
            $otherMosqueCourse
        );

        $student = $this->createStudent('student-api-owner', $ownMosque, $studentRole);
        $otherStudent = $this->createStudent('student-api-other', $ownMosque, $studentRole);
        StudentCircle::create(['student' => $student->id, 'circle' => $ownCircle->id]);
        StudentCircle::create([
            'student' => $otherStudent->id,
            'circle' => $sameMosqueOtherCircle->id,
        ]);

        // This deliberately inconsistent assignment verifies that every query
        // also honors the student's current mosque boundary.
        StudentCircle::create([
            'student' => $student->id,
            'circle' => $outsideMosqueCircle->id,
        ]);

        $ownSubject = Subject::create([
            'name' => 'مادة الطالب',
            'course_id' => $ownCourse->id,
        ]);
        $otherSubject = Subject::create([
            'name' => 'مادة كورس آخر',
            'course_id' => $sameMosqueOtherCourse->id,
        ]);
        $ownLesson = Lesson::create([
            'name' => 'درس الطالب',
            'description' => 'الدرس المخصص لجدول كورس الطالب.',
            'start_page' => 1,
            'end_page' => 3,
            'subject_id' => $ownSubject->id,
        ]);
        $otherLesson = Lesson::create([
            'name' => 'درس كورس آخر',
            'description' => 'يجب ألا يظهر حتى لو ربطته بيانات غير متسقة باليوم.',
            'start_page' => 10,
            'end_page' => 12,
            'subject_id' => $otherSubject->id,
        ]);
        $ownDate = CourseDate::create([
            'course_id' => $ownCourse->id,
            'session_date' => '2026-08-01',
        ]);
        $ownDate->lessons()->attach([$ownLesson->id, $otherLesson->id]);
        $otherDate = CourseDate::create([
            'course_id' => $sameMosqueOtherCourse->id,
            'session_date' => '2026-08-02',
        ]);
        $otherDate->lessons()->attach($otherLesson->id);

        $this->authenticateMobile($student);

        $this->getJson('/api/mobile/student/me/mosque')
            ->assertOk()
            ->assertJsonPath('data.id', $ownMosque->id)
            ->assertJsonPath('data.name', 'مسجد الطالب')
            ->assertJsonMissing(['next_student_sequence']);

        $this->getJson('/api/mobile/student/me/circles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownCircle->id)
            ->assertJsonPath('data.0.course.id', $ownCourse->id);

        $coursesResponse = $this->getJson('/api/mobile/student/me/courses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownCourse->id);

        $this->assertSame(
            [$ownCourse->id],
            collect($coursesResponse->json('data'))->pluck('id')->all()
        );

        $this->getJson("/api/mobile/student/me/courses/{$ownCourse->id}/schedule")
            ->assertOk()
            ->assertJsonPath('data.course.id', $ownCourse->id)
            ->assertJsonCount(1, 'data.schedule')
            ->assertJsonPath('data.schedule.0.session_date', '2026-08-01')
            ->assertJsonCount(1, 'data.schedule.0.lessons')
            ->assertJsonPath('data.schedule.0.lessons.0.id', $ownLesson->id);

        $this->getJson("/api/mobile/student/me/courses/{$sameMosqueOtherCourse->id}/schedule")
            ->assertNotFound()
            ->assertJsonPath('data', null);
        $this->getJson("/api/mobile/student/me/courses/{$otherMosqueCourse->id}/schedule")
            ->assertNotFound()
            ->assertJsonPath('data', null);
    }

    public function test_student_permissions_do_not_open_global_administration_endpoints(): void
    {
        $studentRole = $this->createRole('student-global-scope-check', RoleFamily::Student);
        $studentRole->givePermissionTo(array_values(config('roles.student_capabilities')));
        $student = $this->createStudent('student-global-scope-check', null, $studentRole);

        $this->authenticateMobile($student);

        foreach ([
            '/api/mosque/getAllMosques',
            '/api/circle/getAllCircles',
            '/api/course/getAllCourses',
            '/api/courseDate/getDatesByCourse/1',
            '/api/dateLesson/getCurriculumByCourse/1',
            '/api/lesson/getAllLessons',
            '/api/note/getMyNotes',
            '/api/sabr/getMySabrs',
            '/api/memorization/getMyMemorizations',
            '/api/warning/getMyWarnings',
            '/api/exam/myExams',
        ] as $endpoint) {
            $this->getJson($endpoint)->assertForbidden();
        }
    }

    public function test_student_record_endpoints_return_only_the_authenticated_students_records(): void
    {
        $studentRole = $this->createRole('student-record-role', RoleFamily::Student);
        $studentRole->givePermissionTo(array_values(config('roles.student_capabilities')));

        // Users and students have independent sequences. The deliberately
        // overlapping IDs reproduce the ambiguity in the previous logic.
        $matchedStaff = $this->createUser('matching-staff@example.com', '0990000010');
        $recordAuthor = $this->createUser('record-author@example.com', '0990000011');
        $mosque = Mosque::create([
            'name' => 'مسجد سجلات الطالب',
            'mosque_code' => 'RECORD01',
        ]);
        $project = Project::create([
            'name' => 'مشروع سجلات الطالب',
            'description' => 'مشروع لاختبار عزل السجلات الشخصية.',
            'audience' => 'الطلاب',
            'supervisor' => $recordAuthor->id,
            'is_active' => true,
        ]);
        $course = $this->createCourse(
            'كورس سجلات الطالب',
            $mosque,
            $project,
            $recordAuthor
        );
        $subject = Subject::create([
            'name' => 'مادة سجلات الطالب',
            'course_id' => $course->id,
        ]);

        $student = $this->createStudent('student-record-owner', $mosque, $studentRole);
        $otherStudent = $this->createStudent('student-record-other', $mosque, $studentRole);
        $this->assertSame($matchedStaff->id, $student->id);

        $ownNote = Note::create([
            'student_id' => $student->id,
            'user_id' => $recordAuthor->id,
            'title' => 'ملاحظة الطالب',
            'description' => 'يجب أن تظهر للطالب.',
        ]);
        Note::create([
            'student_id' => $otherStudent->id,
            'user_id' => $matchedStaff->id,
            'title' => 'ملاحظة طالب آخر',
            'description' => 'لا يجوز أن تظهر بسبب تطابق رقم الكاتب.',
        ]);
        $ownSabr = Sabr::create([
            'student' => $student->id,
            'giver' => $recordAuthor->id,
            'course' => $course->id,
            'value' => 'جيد جداً',
            'type' => 'اختبار',
            'date' => '2026-07-20',
            'parts' => [1],
        ]);
        Sabr::create([
            'student' => $otherStudent->id,
            'giver' => $matchedStaff->id,
            'course' => $course->id,
            'value' => 'ممتاز',
            'type' => 'اختبار',
            'date' => '2026-07-21',
            'parts' => [2],
        ]);
        $ownMemorization = Memorization::create([
            'student' => $student->id,
            'giver' => $recordAuthor->id,
            'page_number' => 10,
            'name' => 'تسميع الطالب',
        ]);
        Memorization::create([
            'student' => $otherStudent->id,
            'giver' => $matchedStaff->id,
            'page_number' => 11,
            'name' => 'تسميع طالب آخر',
        ]);
        $ownWarning = Warning::create([
            'student' => $student->id,
            'warner' => $recordAuthor->id,
            'title' => 'إنذار الطالب',
            'description' => 'إنذار يخص الطالب الحالي.',
        ]);
        Warning::create([
            'student' => $otherStudent->id,
            'warner' => $matchedStaff->id,
            'title' => 'إنذار طالب آخر',
            'description' => 'لا يجوز أن يظهر بسبب تطابق رقم المنذر.',
        ]);
        $ownExam = Exam::create([
            'student' => $student->id,
            'subject' => $subject->id,
            'course' => $course->id,
            'mark' => 88,
        ]);
        Exam::create([
            'student' => $otherStudent->id,
            'subject' => $subject->id,
            'course' => $course->id,
            'mark' => 99,
        ]);

        $this->authenticateMobile($student);

        $this->getJson("/api/mobile/student/me/notes?student_id={$otherStudent->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownNote->id);
        $this->getJson('/api/mobile/student/me/sabrs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownSabr->id);
        $this->getJson('/api/mobile/student/me/memorizations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.memorization_id', $ownMemorization->id);
        $this->getJson('/api/mobile/student/me/warnings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownWarning->id);
        $this->getJson('/api/mobile/student/me/exams')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownExam->id);
    }

    public function test_student_self_service_routes_require_authentication_and_the_exact_permission(): void
    {
        foreach ([
            '/api/mobile/student/me/mosque',
            '/api/mobile/student/me/circles',
            '/api/mobile/student/me/courses',
            '/api/mobile/student/me/courses/1/schedule',
            '/api/mobile/student/me/notes',
            '/api/mobile/student/me/sabrs',
            '/api/mobile/student/me/memorizations',
            '/api/mobile/student/me/warnings',
            '/api/mobile/student/me/exams',
        ] as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }

        $studentRole = $this->createRole('student-without-self-service', RoleFamily::Student);
        $student = $this->createStudent('student-no-api-permissions', null, $studentRole);
        $this->authenticateMobile($student);

        foreach ([
            '/api/mobile/student/me/mosque',
            '/api/mobile/student/me/circles',
            '/api/mobile/student/me/courses',
            '/api/mobile/student/me/courses/1/schedule',
            '/api/mobile/student/me/notes',
            '/api/mobile/student/me/sabrs',
            '/api/mobile/student/me/memorizations',
            '/api/mobile/student/me/warnings',
            '/api/mobile/student/me/exams',
        ] as $endpoint) {
            $this->getJson($endpoint)->assertForbidden();
        }
    }

    public function test_non_student_accounts_cannot_use_student_routes_even_with_permissions(): void
    {
        $teacherRole = $this->createRole('mobile-teacher', RoleFamily::Teacher);
        $user = $this->createUser('staff-with-student-permissions@example.com', '0990000003');
        $user->syncRoles([$teacherRole]);
        $user->givePermissionTo(array_values(config('roles.student_capabilities')));
        $this->authenticateMobile($user);

        foreach ([
            '/api/mobile/student/me/mosque',
            '/api/mobile/student/me/circles',
            '/api/mobile/student/me/courses',
            '/api/mobile/student/me/courses/1/schedule',
            '/api/mobile/student/me/notes',
            '/api/mobile/student/me/sabrs',
            '/api/mobile/student/me/memorizations',
            '/api/mobile/student/me/warnings',
            '/api/mobile/student/me/exams',
        ] as $endpoint) {
            $this->getJson($endpoint)
                ->assertForbidden()
                ->assertJsonPath(
                    'message',
                    'هذه الواجهات متاحة لحسابات الطلاب فقط.'
                );
        }
    }

    private function authenticateMobile(Student|User $account): void
    {
        $token = $account->createToken(
            'student-api-test-device',
            [config('auth_tokens.mobile.abilities.access')],
            now()->addHour()
        );

        $this->withToken($token->plainTextToken);
    }

    private function createRole(string $name, RoleFamily $family): Role
    {
        return Role::create([
            'name' => $name,
            'guard_name' => 'web',
            'role_family' => $family->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);
    }

    private function createUser(string $email, string $phone): User
    {
        return User::create([
            'first_name' => 'مستخدم',
            'last_name' => 'اختبار',
            'email' => $email,
            'phone' => $phone,
            'birth_date' => '1990-01-01',
            'password' => 'password123',
        ]);
    }

    private function createCourse(
        string $name,
        Mosque $mosque,
        Project $project,
        User $supervisor
    ): Course {
        return Course::create([
            'name' => $name,
            'description' => 'كورس لاختبار واجهات الطالب.',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'supervisor_id' => $supervisor->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);
    }

    private function createCircle(string $name, User $teacher, Course $course): Circle
    {
        return Circle::create([
            'name' => $name,
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
        ]);
    }

    private function createStudent(
        string $username,
        ?Mosque $mosque,
        Role $role
    ): Student {
        $student = Student::create([
            'mosque_id' => $mosque?->id,
            'first_name' => 'طالب',
            'last_name' => 'اختبار',
            'username' => $username,
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_2',
            'father_name' => 'ولي الأمر',
            'parent_social_state' => 'married',
            'father_phone' => '098'.str_pad((string) Student::count(), 7, '0', STR_PAD_LEFT),
            'password' => 'student123',
        ]);
        $student->syncRoles([$role]);

        return $student;
    }
}
