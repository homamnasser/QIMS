<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Models\Circle;
use App\Models\Course;
use App\Models\EvaluationCandidate;
use App\Models\EvaluationCandidateEnrollment;
use App\Models\EvaluationCycle;
use App\Models\EvaluationPeriod;
use App\Models\EvaluationPolicy;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * يحرس عقد استجابات الخطأ في نظام التقييم النهائي:
 * شكل موحد، رسالة عربية واحدة قصيرة، ورمز حالة HTTP صحيح،
 * ومنع تسرب أسماء الموديلات أو جمل SQL أو أثر الاستدعاء إلى الواجهة.
 */
class EvaluationErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_the_standard_arabic_envelope(): void
    {
        $response = $this->getJson('/api/mobile/teacher/evaluation-candidates')
            ->assertStatus(401)
            ->assertJsonPath('code', 401)
            ->assertJsonPath('error_code', 'UNAUTHENTICATED');

        $this->assertArabic($response->json('message'));
    }

    public function test_missing_candidate_returns_404_without_leaking_the_model_class(): void
    {
        $this->authenticateTeacher($this->context());

        $response = $this->getJson('/api/mobile/teacher/evaluation-candidates/999999/review')
            ->assertStatus(404)
            ->assertJsonPath('code', 404)
            ->assertJsonPath('error_code', 'NOT_FOUND');

        $message = $response->json('message');
        $this->assertArabic($message);
        $this->assertStringNotContainsString('App\\Models', $message);
        $this->assertStringNotContainsString('No query results', $message);
    }

    public function test_forbidden_candidate_returns_403_with_an_arabic_message(): void
    {
        $context = $this->context();
        $this->authenticateTeacher($context, $this->createUser('intruder@example.com', '0991000098'));

        $response = $this->getJson(
            "/api/mobile/teacher/evaluation-candidates/{$context['candidate']->id}/review"
        )->assertStatus(403)->assertJsonPath('error_code', 'FORBIDDEN');

        $this->assertArabic($response->json('message'));
    }

    public function test_validation_failure_names_the_field_in_arabic(): void
    {
        $context = $this->context();
        $this->authenticateTeacher($context);

        $response = $this->putJson(
            "/api/mobile/teacher/evaluation-candidates/{$context['candidate']->id}/teacher-evaluation",
            ['comments' => 'بلا درجات']
        )->assertStatus(422)
            ->assertJsonPath('code', 422)
            ->assertJsonPath('error_code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['code', 'error_code', 'message', 'errors']);

        $this->assertSame(
            'حقل درجة السلوك مطلوب.',
            $response->json('errors.behavior_score.0')
        );
        $this->assertArabic($response->json('message'));
    }

    public function test_out_of_range_teacher_score_reports_the_field_label(): void
    {
        $context = $this->context();
        $this->authenticateTeacher($context);

        $response = $this->putJson(
            "/api/mobile/teacher/evaluation-candidates/{$context['candidate']->id}/teacher-evaluation",
            [
                'evaluation_period_id' => $context['periods'][0]->id,
                'circle_id' => $context['circle']->id,
                'behavior_score' => 500,
                'participation_score' => 10,
                'teacher_opinion_score' => 5,
                'status' => 'submitted',
            ]
        )->assertStatus(422);

        $this->assertStringContainsString(
            'درجة السلوك',
            $response->json('errors.behavior_score.0')
        );
    }

    public function test_database_failure_is_logged_but_never_returned_to_the_client(): void
    {
        Route::middleware('api')->get('/api/test-database-failure', function (): void {
            throw new QueryException(
                'mysql',
                'select * from `evaluation_results` where `secret_column` = ?',
                ['leaked-value'],
                new RuntimeException('SQLSTATE[42S22]: Column not found: 1054 Unknown column')
            );
        });

        $response = $this->getJson('/api/test-database-failure')
            ->assertStatus(500)
            ->assertJsonPath('error_code', 'DATABASE_ERROR');

        $message = $response->json('message');
        $this->assertArabic($message);
        foreach (['SQLSTATE', 'select *', 'secret_column', 'leaked-value'] as $technicalFragment) {
            $this->assertStringNotContainsString($technicalFragment, $message);
        }
    }

    public function test_unexpected_failure_returns_a_generic_arabic_server_error(): void
    {
        Route::middleware('api')->get('/api/test-server-failure', function (): void {
            throw new RuntimeException('Undefined array key "policy_snapshot" in EvaluationRunService.php:142');
        });

        $response = $this->getJson('/api/test-server-failure')
            ->assertStatus(500)
            ->assertJsonPath('error_code', 'SERVER_ERROR');

        $message = $response->json('message');
        $this->assertArabic($message);
        $this->assertStringNotContainsString('EvaluationRunService', $message);
        $this->assertStringNotContainsString('Undefined array key', $message);
    }

    private function assertArabic(?string $message): void
    {
        $this->assertIsString($message);
        $this->assertNotSame('', trim($message));
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $message);
    }

    private function authenticateTeacher(array $context, ?User $as = null): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'evaluation-error-teacher', 'guard_name' => 'web'],
            [
                'role_family' => RoleFamily::Teacher->value,
                'is_system' => false,
                'role_family_reviewed_at' => now(),
            ]
        );
        $role->givePermissionTo(['إدخال تقييم المدرس', 'إدخال تقييم القرآن']);

        $user = $as ?? $context['teacher'];
        $user->syncRoles([$role]);
        $this->app['auth']->forgetGuards();
        $this->withToken(
            $user->createToken(
                'evaluation-error-test',
                [config('auth_tokens.mobile.abilities.access')],
                now()->addHour()
            )->plainTextToken
        );
    }

    private function context(): array
    {
        Date::setTestNow('2026-01-10 12:00:00');
        $supervisor = $this->createUser('error-supervisor@example.com', '0991000101');
        $teacher = $this->createUser('error-teacher@example.com', '0991000102');
        $mosque = Mosque::create(['name' => 'مسجد الأخطاء', 'mosque_code' => 'ERR01']);
        $project = Project::create([
            'name' => 'مشروع الأخطاء',
            'description' => 'اختبار عقد استجابات الخطأ.',
            'audience' => 'الطلاب',
            'supervisor' => $supervisor->id,
            'is_active' => true,
        ]);
        $course = Course::create([
            'name' => 'مقرر الأخطاء',
            'description' => 'مقرر',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'supervisor_id' => $supervisor->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_active' => true,
        ]);
        $circle = Circle::create([
            'name' => 'حلقة الأخطاء',
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'quran_mode' => 'none',
        ]);
        $student = Student::create([
            'mosque_id' => $mosque->id,
            'first_name' => 'طالب',
            'last_name' => 'الأخطاء',
            'username' => 'error-owner',
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_2',
            'father_name' => 'ولي الأمر',
            'parent_social_state' => 'married',
            'father_phone' => '0980000001',
            'password' => 'student123',
        ]);
        $policy = EvaluationPolicy::create([
            'name' => 'سياسة الأخطاء',
            'version' => 1,
            'status' => 'approved',
            'configuration' => config('evaluation.default_policy'),
            'approved_by' => $supervisor->id,
            'approved_at' => now(),
        ]);
        $cycle = EvaluationCycle::create([
            'project_id' => $project->id,
            'policy_id' => $policy->id,
            'name' => 'شتاء 2026',
            'season' => 'winter',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'data_collection',
            'top_students_count' => 15,
            'created_by' => $supervisor->id,
        ]);
        $cycle->courses()->attach($course->id);
        $periods = collect([
            ['الفترة الأولى', 1, '2026-01-01', '2026-01-15'],
            ['الفترة الثانية', 2, '2026-01-16', '2026-01-31'],
        ])->map(fn ($data) => EvaluationPeriod::create([
            'evaluation_cycle_id' => $cycle->id,
            'name' => $data[0],
            'sequence' => $data[1],
            'start_date' => $data[2],
            'end_date' => $data[3],
            'status' => 'data_collection',
        ]))->values();
        $candidate = EvaluationCandidate::create([
            'evaluation_cycle_id' => $cycle->id,
            'student_id' => $student->id,
            'mosque_id' => $mosque->id,
            'academic_class_snapshot' => $student->academic_class,
            'reading_level_snapshot' => $student->reading_level,
            'status' => 'active',
        ]);
        EvaluationCandidateEnrollment::create([
            'evaluation_candidate_id' => $candidate->id,
            'course_id' => $course->id,
            'circle_id' => $circle->id,
            'teacher_id' => $teacher->id,
            'course_name_snapshot' => $course->name,
            'circle_name_snapshot' => $circle->name,
            'quran_mode_snapshot' => 'none',
            'teacher_name_snapshot' => trim($teacher->first_name.' '.$teacher->last_name),
        ]);

        return compact('supervisor', 'teacher', 'mosque', 'circle', 'cycle', 'periods', 'candidate');
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
}
