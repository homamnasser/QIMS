<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Models\Circle;
use App\Models\Course;
use App\Models\EvaluationCandidate;
use App\Models\EvaluationCandidateEnrollment;
use App\Models\EvaluationCycle;
use App\Models\EvaluationPolicy;
use App\Models\Exam;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileStaffAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_and_field_supervisor_can_login_with_mobile_compatibility_fields(): void
    {
        $teacherRole = $this->createRole('mobile-staff-teacher', RoleFamily::Teacher);
        $teacher = $this->createUser('mobile-teacher@example.com', '0992000001');
        $teacher->syncRoles([$teacherRole]);

        $this->postJson('/api/mobile/auth/login', [
            'email' => $teacher->email,
            'password' => 'password123',
            'device_name' => 'Teacher Phone',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.role_family', RoleFamily::Teacher->value)
            ->assertJsonPath('data.user.account_type', 'staff')
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);

        $fieldSupervisor = $this->createFieldSupervisor(
            'mobile-field-supervisor@example.com',
            '0992000002'
        );

        $this->postJson('/api/mobile/auth/login', [
            'login' => $fieldSupervisor->email,
            'password' => 'password123',
            'device_name' => 'Field Supervisor Phone',
        ])
            ->assertOk()
            ->assertJsonPath(
                'data.user.role_family',
                RoleFamily::FieldSupervisor->value
            )
            ->assertJsonPath('data.user.has_full_field_operations_access', true);

        $adminRole = $this->createRole('mobile-staff-admin', RoleFamily::Admin);
        $admin = $this->createUser('mobile-admin@example.com', '0992000003');
        $admin->syncRoles([$adminRole]);

        $this->postJson('/api/mobile/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
            'device_name' => 'Admin Phone',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'AUTHENTICATION_FAILED');
    }

    public function test_mobile_staff_routes_require_both_staff_channel_and_permission(): void
    {
        $teacherRole = $this->createRole('permission-mobile-teacher', RoleFamily::Teacher);
        $teacher = $this->createUser('permission-teacher@example.com', '0992000010');
        $teacher->syncRoles([$teacherRole]);
        $teacherToken = $this->accessToken($teacher);

        $this->withFreshBearer($teacherToken)
            ->getJson('/api/mobile/staff/attendance')
            ->assertForbidden();

        $teacherRole->givePermissionTo('عرض كافة الغيابات');
        $this->withFreshBearer($teacherToken)
            ->getJson('/api/mobile/staff/attendance')
            ->assertOk();

        $studentRole = $this->createRole('mobile-channel-student', RoleFamily::Student);
        $studentRole->givePermissionTo('عرض كافة الغيابات');
        $student = $this->createStudent('mobile-channel-student');
        $student->syncRoles([$studentRole]);

        $this->withFreshBearer($this->accessToken($student))
            ->getJson('/api/mobile/staff/attendance')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'AUTH_CHANNEL_MISMATCH');

        $fieldSupervisor = $this->createFieldSupervisor(
            'channel-field-supervisor@example.com',
            '0992000011'
        );
        $fieldToken = $this->accessToken($fieldSupervisor);

        $this->withFreshBearer($fieldToken)
            ->getJson('/api/mobile/staff/attendance')
            ->assertOk();
        $this->withFreshBearer($fieldToken)
            ->getJson('/api/mobile/student/me/mosque')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'AUTH_CHANNEL_MISMATCH');
    }

    public function test_field_supervisor_role_is_protected_and_contains_every_required_permission(): void
    {
        $role = Role::where('name', 'field-supervisor')->firstOrFail();

        $this->assertTrue($role->is_system);
        $this->assertSame(RoleFamily::FieldSupervisor, $role->role_family);

        foreach (config('roles.field_supervisor_permissions') as $permission) {
            $this->assertTrue(
                $role->hasPermissionTo($permission),
                "Missing field supervisor permission: {$permission}"
            );
        }
    }

    public function test_teacher_my_exams_are_scoped_to_students_in_their_own_circles(): void
    {
        $teacherRole = $this->createRole('exam-mobile-teacher', RoleFamily::Teacher);
        $teacherRole->givePermissionTo(Permission::firstOrCreate([
            'name' => 'امتحاناتي',
            'guard_name' => 'web',
        ]));
        $teacher = $this->createUser('exam-teacher@example.com', '0992000015');
        $teacher->syncRoles([$teacherRole]);
        $otherTeacher = $this->createUser(
            'other-exam-teacher@example.com',
            '0992000016'
        );
        [$ownCourse] = $this->createCourseContext($teacher);
        [$otherCourse] = $this->createCourseContext($otherTeacher);
        $ownCircle = Circle::create([
            'name' => 'حلقة امتحانات المعلم',
            'teacher_id' => $teacher->id,
            'course_id' => $ownCourse->id,
            'quran_mode' => 'recitation',
        ]);
        $otherCircle = Circle::create([
            'name' => 'حلقة امتحانات معلم آخر',
            'teacher_id' => $otherTeacher->id,
            'course_id' => $otherCourse->id,
            'quran_mode' => 'recitation',
        ]);
        $ownStudent = $this->createStudent('own-exam-student');
        $otherStudent = $this->createStudent('other-exam-student');
        StudentCircle::create([
            'student' => $ownStudent->id,
            'circle' => $ownCircle->id,
        ]);
        StudentCircle::create([
            'student' => $otherStudent->id,
            'circle' => $otherCircle->id,
        ]);
        $ownSubject = Subject::create([
            'name' => 'مادة امتحان المعلم',
            'course_id' => $ownCourse->id,
        ]);
        $otherSubject = Subject::create([
            'name' => 'مادة امتحان معلم آخر',
            'course_id' => $otherCourse->id,
        ]);
        $ownExam = Exam::create([
            'student' => $ownStudent->id,
            'subject' => $ownSubject->id,
            'course' => $ownCourse->id,
            'mark' => 90,
        ]);
        Exam::create([
            'student' => $otherStudent->id,
            'subject' => $otherSubject->id,
            'course' => $otherCourse->id,
            'mark' => 80,
        ]);

        $this->withFreshBearer($this->accessToken($teacher))
            ->getJson('/api/mobile/staff/exams/mine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownExam->id);
    }

    public function test_field_supervisor_can_manage_records_created_by_other_staff(): void
    {
        $fieldSupervisor = $this->createFieldSupervisor(
            'records-field-supervisor@example.com',
            '0992000020'
        );
        $author = $this->createUser('records-author@example.com', '0992000021');
        $student = $this->createStudent('records-field-student');
        [$course] = $this->createCourseContext($author);

        $note = Note::create([
            'student_id' => $student->id,
            'user_id' => $author->id,
            'title' => 'ملاحظة كتبها موظف آخر',
            'description' => 'يجب أن يستطيع المشرف الميداني إدارتها.',
        ]);
        $warning = Warning::create([
            'student' => $student->id,
            'warner' => $author->id,
            'title' => 'إنذار كتبه موظف آخر',
            'description' => 'سجل تشغيلي تحت الإشراف الميداني.',
        ]);
        $sabr = Sabr::create([
            'student' => $student->id,
            'giver' => $author->id,
            'course' => $course->id,
            'value' => 'جيد',
            'type' => 'داخلي',
            'date' => '2026-08-01',
            'parts' => [1],
            'note' => 'قبل التحديث',
        ]);

        $token = $this->accessToken($fieldSupervisor);

        $this->withFreshBearer($token)
            ->deleteJson("/api/mobile/staff/notes/{$note->id}")
            ->assertOk();
        $this->withFreshBearer($token)
            ->deleteJson("/api/mobile/staff/warnings/{$warning->id}")
            ->assertOk();
        $this->withFreshBearer($token)
            ->putJson("/api/mobile/staff/sabrs/{$sabr->id}", [
                'value' => 'ممتاز',
                'note' => 'تمت المراجعة ميدانياً',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
        $this->assertDatabaseMissing('warnings', ['id' => $warning->id]);
        $this->assertDatabaseHas('sabrs', [
            'id' => $sabr->id,
            'value' => 'ممتاز',
            'note' => 'تمت المراجعة ميدانياً',
        ]);
    }

    public function test_field_supervisor_sees_all_evaluation_candidates_while_teacher_remains_scoped(): void
    {
        $fieldSupervisor = $this->createFieldSupervisor(
            'evaluation-field-supervisor@example.com',
            '0992000030'
        );
        $assignedTeacher = $this->createUser(
            'assigned-evaluation-teacher@example.com',
            '0992000031'
        );
        $unassignedTeacherRole = $this->createRole(
            'unassigned-evaluation-teacher',
            RoleFamily::Teacher
        );
        $unassignedTeacherRole->givePermissionTo('إدخال تقييم المدرس');
        $unassignedTeacher = $this->createUser(
            'unassigned-evaluation-teacher@example.com',
            '0992000032'
        );
        $unassignedTeacher->syncRoles([$unassignedTeacherRole]);
        $student = $this->createStudent('evaluation-field-student');
        [$course, $project, $mosque] = $this->createCourseContext($assignedTeacher);
        $circle = Circle::create([
            'name' => 'حلقة التقييم الميداني',
            'teacher_id' => $assignedTeacher->id,
            'course_id' => $course->id,
            'quran_mode' => 'recitation',
        ]);
        $policy = EvaluationPolicy::create([
            'name' => 'سياسة اختبار الإشراف الميداني',
            'version' => 1,
            'status' => 'approved',
            'configuration' => config('evaluation.default_policy'),
            'approved_by' => $assignedTeacher->id,
            'approved_at' => now(),
        ]);
        $cycle = EvaluationCycle::create([
            'project_id' => $project->id,
            'policy_id' => $policy->id,
            'name' => 'دورة اختبار الإشراف الميداني',
            'season' => 'summer',
            'start_date' => '2026-07-01',
            'end_date' => '2026-08-31',
            'status' => 'data_collection',
            'created_by' => $assignedTeacher->id,
        ]);
        $candidate = EvaluationCandidate::create([
            'evaluation_cycle_id' => $cycle->id,
            'student_id' => $student->id,
            'mosque_id' => $mosque->id,
            'status' => 'active',
        ]);
        EvaluationCandidateEnrollment::create([
            'evaluation_candidate_id' => $candidate->id,
            'course_id' => $course->id,
            'circle_id' => $circle->id,
            'teacher_id' => $assignedTeacher->id,
            'course_name_snapshot' => $course->name,
            'circle_name_snapshot' => $circle->name,
            'teacher_name_snapshot' => 'المعلم المسند',
            'quran_mode_snapshot' => 'recitation',
        ]);

        $this->withFreshBearer($this->accessToken($fieldSupervisor))
            ->getJson('/api/mobile/staff/evaluation-candidates')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $candidate->id);

        $this->withFreshBearer($this->accessToken($unassignedTeacher))
            ->getJson('/api/mobile/staff/evaluation-candidates')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    private function createFieldSupervisor(string $email, string $phone): User
    {
        $user = $this->createUser($email, $phone);
        $user->syncRoles([
            Role::where('name', 'field-supervisor')->firstOrFail(),
        ]);

        return $user;
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

    private function createStudent(string $username): Student
    {
        return Student::create([
            'first_name' => 'طالب',
            'last_name' => 'اختبار',
            'username' => $username,
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_2',
            'father_name' => 'ولي الأمر',
            'parent_social_state' => 'married',
            'father_phone' => '098'.str_pad(
                (string) Student::count(),
                7,
                '0',
                STR_PAD_LEFT
            ),
            'password' => 'student123',
        ]);
    }

    /**
     * @return array{Course, Project, Mosque}
     */
    private function createCourseContext(User $supervisor): array
    {
        $mosque = Mosque::create([
            'name' => 'مسجد اختبار '.uniqid(),
            'mosque_code' => sprintf('FS%010d', Mosque::count() + 1),
        ]);
        $project = Project::create([
            'name' => 'مشروع اختبار '.uniqid(),
            'description' => 'مشروع لاختبار صلاحيات الإشراف الميداني.',
            'audience' => 'الطلاب',
            'supervisor' => $supervisor->id,
            'is_active' => true,
        ]);
        $course = Course::create([
            'name' => 'كورس اختبار '.uniqid(),
            'description' => 'كورس لاختبار صلاحيات الموبايل.',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'supervisor_id' => $supervisor->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-08-31',
            'is_active' => true,
        ]);

        return [$course, $project, $mosque];
    }

    private function accessToken(User|Student $account): string
    {
        return $account->createToken(
            'mobile-staff-test',
            [config('auth_tokens.mobile.abilities.access')],
            now()->addHour()
        )->plainTextToken;
    }

    private function withFreshBearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
