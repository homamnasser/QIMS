<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Enums\StaffWorkScope;
use App\Models\Course;
use App\Models\Memorization;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\Role;
use App\Models\SabrPartAchievement;
use App\Models\Student;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Support\StaffScopeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StaffWorkScopeTest extends TestCase
{
    use RefreshDatabase;

    private Mosque $othmanMosque;

    private Mosque $otherMosque;

    private Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->othmanMosque = Mosque::query()->create([
            'name' => 'مسجد عثمان',
            'mosque_code' => 'OTH',
        ]);
        $this->otherMosque = Mosque::query()->create([
            'name' => 'مسجد آخر',
            'mosque_code' => 'OTH2',
        ]);

        $this->staffRole = Role::query()->create([
            'name' => 'general-supervisor-test',
            'guard_name' => 'web',
            'role_family' => RoleFamily::Supervisor->value,
            'is_system' => false,
            'role_family_reviewed_at' => now(),
        ]);

        foreach ([
            'إنشاء موظف',
            'تعديل موظف',
            'عرض كافة الموظفين',
            'عرض تفاصيل الموظف',
            'عرض كافة المساجد',
            'حذف مسجد',
            'عرض كافة الكورسات',
            'عرض تفاصيل الكورس',
            'إنشاء كورس',
            'الإشراف على المشاريع والكورسات',
            'إنشاء مشروع',
            'عرض تفاصيل الاستبيان',
            'عرض كافة الطلاب',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_institute_staff_must_choose_a_mosque_for_single_mosque_scope(): void
    {
        $admin = $this->createStaff(
            'institute-admin@example.com',
            StaffWorkScope::Institute
        );
        $admin->givePermissionTo('إنشاء موظف');

        $payload = $this->staffPayload([
            'email' => 'new-staff@example.com',
            'phone' => '0990000002',
            'work_scope' => StaffWorkScope::Mosque->value,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/createStaffMember', $payload)
            ->assertUnprocessable()
            ->assertJsonPath(
                'message.mosque_id.0',
                'يجب اختيار مسجد واحد للموظف.'
            );

        $this->actingAs($admin)
            ->postJson('/api/createStaffMember', [
                ...$payload,
                'mosque_id' => $this->othmanMosque->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.work_scope', StaffWorkScope::Mosque->value)
            ->assertJsonPath('data.mosque_id', $this->othmanMosque->id)
            ->assertJsonPath('data.mosque.name', 'مسجد عثمان');
    }

    public function test_mosque_supervisor_cannot_escape_scope_when_creating_staff(): void
    {
        $supervisor = $this->createStaff(
            'othman-supervisor@example.com',
            StaffWorkScope::Mosque,
            $this->othmanMosque
        );
        $supervisor->givePermissionTo([
            'إنشاء موظف',
            'عرض كافة الموظفين',
            'عرض تفاصيل الموظف',
        ]);

        $otherStaff = $this->createStaff(
            'other-staff@example.com',
            StaffWorkScope::Mosque,
            $this->otherMosque
        );
        $instituteStaff = $this->createStaff(
            'global-staff@example.com',
            StaffWorkScope::Institute
        );

        $response = $this->actingAs($supervisor)
            ->postJson('/api/createStaffMember', $this->staffPayload([
                'email' => 'locked-staff@example.com',
                'phone' => '0990000006',
                'work_scope' => StaffWorkScope::Institute->value,
                'mosque_id' => $this->otherMosque->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.work_scope', StaffWorkScope::Mosque->value)
            ->assertJsonPath('data.mosque_id', $this->othmanMosque->id);

        $createdId = $response->json('data.id');
        $this->assertDatabaseHas('users', [
            'id' => $createdId,
            'work_scope' => StaffWorkScope::Mosque->value,
            'mosque_id' => $this->othmanMosque->id,
        ]);

        $list = $this->actingAs($supervisor)
            ->getJson('/api/getAllStaff')
            ->assertOk()
            ->json('data');

        $visibleIds = collect($list)->pluck('id');
        $this->assertTrue($visibleIds->contains($supervisor->id));
        $this->assertTrue($visibleIds->contains($createdId));
        $this->assertFalse($visibleIds->contains($otherStaff->id));
        $this->assertFalse($visibleIds->contains($instituteStaff->id));

        $this->actingAs($supervisor)
            ->getJson('/api/getStaffById/'.$otherStaff->id)
            ->assertNotFound();
    }

    public function test_scope_options_are_locked_to_the_supervisors_own_mosque(): void
    {
        $supervisor = $this->createStaff(
            'scope-options@example.com',
            StaffWorkScope::Mosque,
            $this->othmanMosque
        );
        $supervisor->givePermissionTo('إنشاء موظف');

        $this->actingAs($supervisor)
            ->getJson('/api/staffScopeOptions')
            ->assertOk()
            ->assertJsonPath('data.work_scope_locked', true)
            ->assertJsonPath('data.work_scope', StaffWorkScope::Mosque->value)
            ->assertJsonPath('data.mosque_id', $this->othmanMosque->id)
            ->assertJsonCount(1, 'data.mosques')
            ->assertJsonPath('data.mosques.0.id', $this->othmanMosque->id);
    }

    public function test_operational_entities_and_institute_mutations_are_mosque_scoped(): void
    {
        $supervisor = $this->createStaff(
            'operations@example.com',
            StaffWorkScope::Mosque,
            $this->othmanMosque
        );
        $supervisor->givePermissionTo([
            'عرض كافة المساجد',
            'عرض كافة الكورسات',
            'عرض تفاصيل الكورس',
            'إنشاء كورس',
            'الإشراف على المشاريع والكورسات',
            'إنشاء مشروع',
        ]);

        $project = Project::query()->create([
            'name' => 'مشروع الاختبار',
            'description' => 'وصف المشروع',
            'audience' => 'الطلاب',
            'supervisor' => $supervisor->id,
            'is_active' => true,
        ]);
        $ownCourse = $this->createCourse('كورس عثمان', $this->othmanMosque, $project, $supervisor);
        $otherCourse = $this->createCourse('كورس المسجد الآخر', $this->otherMosque, $project, $supervisor);

        $this->actingAs($supervisor)
            ->getJson('/api/mosque/getAllMosques')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->othmanMosque->id);

        $courses = $this->actingAs($supervisor)
            ->getJson('/api/course/getAllCourses')
            ->assertOk()
            ->json('data');
        $this->assertSame([$ownCourse->id], collect($courses)->pluck('id')->all());

        $this->actingAs($supervisor)
            ->getJson('/api/course/getCourse/'.$otherCourse->id)
            ->assertNotFound();

        $this->actingAs($supervisor)
            ->postJson('/api/course/createCourse', [
                'name' => 'كورس أُرسل بمسجد خاطئ',
                'description' => 'يجب أن يقفله الخادم على مسجد عثمان',
                'mosque_id' => $this->otherMosque->id,
                'project_id' => $project->id,
                'supervisor_id' => $supervisor->id,
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',
            ])
            ->assertCreated()
            ->assertJsonPath('data.mosque_id', $this->othmanMosque->id);

        $this->assertDatabaseHas('courses', [
            'name' => 'كورس أُرسل بمسجد خاطئ',
            'mosque_id' => $this->othmanMosque->id,
        ]);

        $this->actingAs($supervisor)
            ->postJson('/api/project/createProject', [
                'name' => 'مشروع ممنوع',
                'description' => 'لا يجوز إنشاؤه من نطاق مسجد',
                'audience' => 'الطلاب',
                'supervisor' => $supervisor->id,
            ])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'MOSQUE_SCOPE_VIOLATION');
    }

    public function test_mosque_survey_rejects_students_from_another_mosque(): void
    {
        $creator = $this->createStaff(
            'survey-creator@example.com',
            StaffWorkScope::Mosque,
            $this->othmanMosque
        );
        $survey = Survey::query()->create([
            'public_token' => 'mosque-survey-token',
            'name' => 'استبيان مسجد عثمان',
            'status' => Survey::STATUS_PUBLISHED,
            'allow_multiple_responses' => true,
            'created_by' => $creator->id,
            'mosque_id' => $this->othmanMosque->id,
            'published_at' => now(),
        ]);
        $student = Student::query()->create([
            'mosque_id' => $this->otherMosque->id,
            'selfnumber' => 'OTH2-000001',
            'first_name' => 'طالب',
            'last_name' => 'الاختبار',
            'username' => 'other-mosque-student',
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_1',
            'father_name' => 'والد الطالب',
            'parent_social_state' => 'married',
            'father_phone' => '0980000000',
            'password' => 'password123',
        ]);

        $this->postJson(
            '/api/public/surveys/'.$survey->public_token.'/identify',
            ['selfnumber' => $student->selfnumber]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.selfnumber.0',
                'هذا الاستبيان مخصص لطلاب مسجد آخر.'
            );
    }

    public function test_route_model_binding_cannot_resolve_another_mosques_survey(): void
    {
        $supervisor = $this->createStaff(
            'survey-binding@example.com',
            StaffWorkScope::Mosque,
            $this->othmanMosque
        );
        $supervisor->givePermissionTo('عرض تفاصيل الاستبيان');

        $otherCreator = $this->createStaff(
            'other-survey-creator@example.com',
            StaffWorkScope::Mosque,
            $this->otherMosque
        );
        $otherSurvey = Survey::query()->create([
            'public_token' => 'other-mosque-survey',
            'name' => 'استبيان المسجد الآخر',
            'status' => Survey::STATUS_DRAFT,
            'allow_multiple_responses' => false,
            'created_by' => $otherCreator->id,
            'mosque_id' => $this->otherMosque->id,
        ]);

        $this->actingAs($supervisor)
            ->getJson('/api/surveys/'.$otherSurvey->id)
            ->assertNotFound();
    }

    public function test_mosque_with_scoped_staff_cannot_be_deleted_with_a_misleading_course_error(): void
    {
        $admin = $this->createStaff(
            'delete-mosque-admin@example.com',
            StaffWorkScope::Institute
        );
        $admin->givePermissionTo('حذف مسجد');
        $this->createStaff(
            'assigned-to-mosque@example.com',
            StaffWorkScope::Mosque,
            $this->othmanMosque
        );

        $this->actingAs($admin)
            ->deleteJson('/api/mosque/deleteMosque/'.$this->othmanMosque->id)
            ->assertConflict()
            ->assertJsonPath('error_code', 'MOSQUE_HAS_SCOPED_RECORDS')
            ->assertJsonPath('data.staff_count', 1);
    }

    /**
     * Records whose link back to the mosque runs through a nullable foreign key
     * must still reach their own mosque's staff instead of vanishing.
     */
    public function test_records_with_optional_links_stay_visible_to_their_own_mosque(): void
    {
        $supervisor = $this->createStaff(
            'optional-links@example.com',
            StaffWorkScope::Mosque,
            $this->othmanMosque
        );

        $ownStudent = $this->createStudent($this->othmanMosque, 'OTH-000001');
        $otherStudent = $this->createStudent($this->otherMosque, 'OTH2-000001');

        foreach ([$ownStudent, $otherStudent] as $student) {
            Memorization::query()->create([
                'student' => $student->id,
                'giver' => $supervisor->id,
                'page_number' => 3,
                'name' => 'تسميع بلا حلقة',
            ]);
            SabrPartAchievement::query()->create([
                'student_id' => $student->id,
                'evaluation_candidate_id' => null,
                'part_number' => 30,
                'source_type' => 'internal',
                'bonus_points' => 1,
                'first_success_at' => now(),
            ]);
        }

        app(StaffScopeContext::class)->activate($supervisor);

        try {
            $this->assertSame(
                [$ownStudent->id],
                Memorization::query()->pluck('student')->all(),
                'التسميع بلا كورس أو حلقة يجب أن يبقى ظاهراً لمسجده'
            );
            $this->assertSame(
                [$ownStudent->id],
                SabrPartAchievement::query()->pluck('student_id')->all(),
                'إنجاز السبر بلا مرشّح تقييم يجب أن يبقى ظاهراً لمسجده'
            );
        } finally {
            app(StaffScopeContext::class)->clear();
        }
    }

    public function test_survey_response_stays_visible_after_its_student_is_removed(): void
    {
        $supervisor = $this->createStaff(
            'survey-responses@example.com',
            StaffWorkScope::Mosque,
            $this->othmanMosque
        );

        $ownResponse = $this->createSurveyResponse(
            $this->othmanMosque,
            $supervisor,
            'own-mosque-survey'
        );
        $this->createSurveyResponse(
            $this->otherMosque,
            $supervisor,
            'foreign-mosque-survey'
        );

        app(StaffScopeContext::class)->activate($supervisor);

        try {
            $this->assertSame(
                [$ownResponse->id],
                SurveyResponse::query()->pluck('id')->all(),
                'الرد المجهول على استبيان المسجد يجب أن يبقى ظاهراً دون تسريب ردود مسجد آخر'
            );
        } finally {
            app(StaffScopeContext::class)->clear();
        }
    }

    /**
     * موظفو المعهد لا يمرّون بفلترة المسجد إطلاقاً؛ يبقى وصولهم كاملاً لكل
     * المساجد ولا تحدّه إلا صلاحيات دوره.
     */
    public function test_institute_staff_keep_full_cross_mosque_access(): void
    {
        $instituteStaff = $this->createStaff(
            'institute-wide@example.com',
            StaffWorkScope::Institute
        );
        $instituteStaff->givePermissionTo([
            'عرض كافة المساجد',
            'عرض كافة الطلاب',
        ]);

        $ownStudent = $this->createStudent($this->othmanMosque, 'OTH-000002');
        $otherStudent = $this->createStudent($this->otherMosque, 'OTH2-000002');

        foreach ([$ownStudent, $otherStudent] as $student) {
            Memorization::query()->create([
                'student' => $student->id,
                'giver' => $instituteStaff->id,
                'page_number' => 5,
                'name' => 'تسميع بلا حلقة',
            ]);
            SabrPartAchievement::query()->create([
                'student_id' => $student->id,
                'evaluation_candidate_id' => null,
                'part_number' => 29,
                'source_type' => 'internal',
                'bonus_points' => 1,
                'first_success_at' => now(),
            ]);
        }

        $this->createSurveyResponse(
            $this->othmanMosque,
            $instituteStaff,
            'institute-own-survey'
        );
        $this->createSurveyResponse(
            $this->otherMosque,
            $instituteStaff,
            'institute-other-survey'
        );

        // المسار الحقيقي عبر HTTP: يجب ألا تُفعَّل الفلترة أصلاً لهذا الحساب.
        $this->actingAs($instituteStaff)
            ->getJson('/api/mosque/getAllMosques')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($instituteStaff)
            ->getJson('/api/student/getAllStudents')
            ->assertOk();

        $this->assertNull(
            app(StaffScopeContext::class)->staff(),
            'يجب ألا يُسجَّل موظف المعهد في سياق فلترة المسجد'
        );

        $this->assertEqualsCanonicalizing(
            [$ownStudent->id, $otherStudent->id],
            Student::query()->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$ownStudent->id, $otherStudent->id],
            Memorization::query()->pluck('student')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$ownStudent->id, $otherStudent->id],
            SabrPartAchievement::query()->pluck('student_id')->all()
        );
        $this->assertCount(2, SurveyResponse::query()->get());
        $this->assertCount(2, Mosque::query()->get());
    }

    private function createStudent(Mosque $mosque, string $selfnumber): Student
    {
        return Student::query()->create([
            'mosque_id' => $mosque->id,
            'selfnumber' => $selfnumber,
            'first_name' => 'طالب',
            'last_name' => 'الاختبار',
            'username' => 'student-'.strtolower($selfnumber),
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_1',
            'father_name' => 'والد الطالب',
            'parent_social_state' => 'married',
            'father_phone' => '09'.str_pad(
                (string) (Student::query()->withoutGlobalScopes()->count() + 10),
                8,
                '0',
                STR_PAD_LEFT
            ),
            'password' => 'password123',
        ]);
    }

    private function createSurveyResponse(
        Mosque $mosque,
        User $creator,
        string $token
    ): SurveyResponse {
        $survey = Survey::query()->create([
            'public_token' => $token,
            'name' => 'استبيان '.$mosque->name,
            'status' => Survey::STATUS_PUBLISHED,
            'allow_multiple_responses' => true,
            'created_by' => $creator->id,
            'mosque_id' => $mosque->id,
            'published_at' => now(),
        ]);

        return SurveyResponse::query()->create([
            'response_token' => (string) \Illuminate\Support\Str::uuid(),
            'survey_id' => $survey->id,
            // The student row is gone, so the FK was nulled by nullOnDelete().
            'student_id' => null,
            'student_selfnumber_snapshot' => 'DELETED-0001',
            'survey_snapshot' => [],
            'student_data_snapshot' => [],
            'submitted_at' => now(),
        ]);
    }

    private function createStaff(
        string $email,
        StaffWorkScope $scope,
        ?Mosque $mosque = null
    ): User {
        $user = User::query()->create([
            'first_name' => 'موظف',
            'last_name' => 'اختبار',
            'email' => $email,
            'phone' => '09'.str_pad(
                (string) (User::query()->count() + 1),
                8,
                '0',
                STR_PAD_LEFT
            ),
            'birth_date' => '1990-01-01',
            'password' => 'password123',
            'work_scope' => $scope->value,
            'mosque_id' => $mosque?->id,
        ]);
        $user->syncRoles([$this->staffRole]);

        return $user;
    }

    private function staffPayload(array $overrides = []): array
    {
        return [
            'first_name' => 'موظف',
            'last_name' => 'جديد',
            'email' => 'new@example.com',
            'phone' => '0990000001',
            'birth_date' => '1995-01-01',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $this->staffRole->id,
            'work_scope' => StaffWorkScope::Institute->value,
            ...$overrides,
        ];
    }

    private function createCourse(
        string $name,
        Mosque $mosque,
        Project $project,
        User $supervisor
    ): Course {
        return Course::query()->create([
            'name' => $name,
            'description' => 'وصف الكورس',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'supervisor_id' => $supervisor->id,
        ]);
    }
}
