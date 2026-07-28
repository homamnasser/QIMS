<?php

namespace Tests\Feature;

use App\Models\Mosque;
use App\Models\Student;
use App\Models\Survey;
use App\Models\SurveyLogicRule;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\User;
use App\Services\SurveyDefinitionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveySystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_schedule_is_normalized_to_utc_and_available_inside_the_selected_window(): void
    {
        Date::setTestNow(CarbonImmutable::parse('2026-07-24T09:16:00Z'));

        try {
            Storage::fake('public');
            $user = $this->createUser('survey-timezone@example.com', '0990000011');
            $user->givePermissionTo(Permission::findOrCreate('إنشاء استبيان', 'web'));
            $this->actingAs($user, 'web');

            $create = $this->post('/api/surveys', [
                'name' => 'استبيان التوقيت المحلي',
                'description' => 'اختبار نافذة الإتاحة بتوقيت دمشق.',
                'logo' => UploadedFile::fake()->image('timezone-survey.png', 400, 200),
                'starts_at' => '2026-07-24T12:14',
                'ends_at' => '2026-07-24T12:20',
                'allow_multiple_responses' => false,
            ], ['Accept' => 'application/json']);

            $create->assertCreated()
                ->assertJsonPath('data.starts_at', '2026-07-24T09:14:00+00:00')
                ->assertJsonPath('data.ends_at', '2026-07-24T09:20:00+00:00');

            $survey = Survey::findOrFail($create->json('data.id'));
            $survey->forceFill([
                'status' => Survey::STATUS_PUBLISHED,
                'published_at' => now(),
            ])->save();

            $this->getJson("/api/public/surveys/{$survey->public_token}")
                ->assertOk()
                ->assertJsonPath('data.is_available', true)
                ->assertJsonPath('data.starts_at', '2026-07-24T09:14:00+00:00')
                ->assertJsonPath('data.ends_at', '2026-07-24T09:20:00+00:00');
        } finally {
            Date::setTestNow();
        }
    }

    public function test_public_student_flow_uses_selfnumber_conditional_logic_and_single_response_limit(): void
    {
        [$survey, $student, $sourceQuestion] = $this->createPublishedSurvey();

        $identify = $this->postJson("/api/public/surveys/{$survey->public_token}/identify", [
            'selfnumber' => $student->selfnumber,
        ]);

        $identify->assertOk()
            ->assertJsonPath('data.student.selfnumber', 'O-000001')
            ->assertJsonPath('data.survey.student_fields.0.field_key', 'basic.full_name');

        $accessToken = $identify->json('data.access_token');

        $submit = $this->postJson("/api/public/surveys/{$survey->public_token}/responses", [
            'access_token' => $accessToken,
            'answers' => [
                (string) $sourceQuestion->id => 'no',
            ],
            'student_fields' => [],
        ]);

        $submit->assertCreated();
        $this->assertDatabaseCount('survey_responses', 1);
        $this->assertDatabaseHas('survey_answers', [
            'question_id' => $sourceQuestion->id,
            'question_title' => 'هل ترغب بالمتابعة؟',
        ]);

        $response = SurveyResponse::firstOrFail();
        $this->assertSame('O-000001', $response->student_selfnumber_snapshot);
        $this->assertSame('محمد الطالب', $response->student_data_snapshot[0]['value']);

        $this->postJson("/api/public/surveys/{$survey->public_token}/identify", [
            'selfnumber' => $student->selfnumber,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('selfnumber');
    }

    public function test_conditional_required_question_is_validated_only_when_rule_matches(): void
    {
        [$survey, $student, $sourceQuestion, $followUpQuestion] = $this->createPublishedSurvey();

        $token = $this->postJson("/api/public/surveys/{$survey->public_token}/identify", [
            'selfnumber' => $student->selfnumber,
        ])->json('data.access_token');

        $this->postJson("/api/public/surveys/{$survey->public_token}/responses", [
            'access_token' => $token,
            'answers' => [
                (string) $sourceQuestion->id => 'yes',
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors("answers.{$followUpQuestion->id}");
    }

    public function test_publication_validation_rejects_backward_or_circular_dependencies(): void
    {
        [$survey, , $firstQuestion, $secondQuestion] = $this->createPublishedSurvey();

        SurveyLogicRule::create([
            'survey_id' => $survey->id,
            'source_question_id' => $secondQuestion->id,
            'target_type' => 'question',
            'target_question_id' => $firstQuestion->id,
            'action' => 'show_if',
            'operator' => 'contains',
            'values' => ['anything'],
        ]);

        $errors = app(SurveyDefinitionService::class)->publicationErrors($survey);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('يجب أن يسبق السؤال المصدر', implode(' ', $errors));
        $this->assertStringContainsString('حلقة دائرية', implode(' ', $errors));
    }

    public function test_survey_index_requires_the_explicit_permission(): void
    {
        $user = $this->createUser('permissions@example.com', '0990000010');
        $this->actingAs($user, 'web');

        $this->getJson('/api/surveys')->assertForbidden();

        $permission = Permission::findOrCreate('عرض كافة الاستبيانات', 'web');
        $user->givePermissionTo($permission);

        $this->getJson('/api/surveys')->assertOk();
    }

    public function test_authorized_creator_can_create_build_update_and_publish_a_survey(): void
    {
        Storage::fake('public');
        $user = $this->createUser('survey-api@example.com', '0990000020');
        foreach ([
            'إنشاء استبيان',
            'تعديل استبيان',
            'عرض تفاصيل الاستبيان',
            'نشر وإلغاء نشر الاستبيان',
        ] as $permissionName) {
            $user->givePermissionTo(Permission::findOrCreate($permissionName, 'web'));
        }
        $this->actingAs($user, 'web');

        $create = $this->post('/api/surveys', [
            'name' => 'استبيان API',
            'description' => 'مسودة قابلة للبناء.',
            'logo' => UploadedFile::fake()->image('survey.png', 400, 200),
            'allow_multiple_responses' => false,
        ], ['Accept' => 'application/json']);

        $create->assertCreated()
            ->assertJsonPath('data.status', Survey::STATUS_DRAFT);
        $surveyId = $create->json('data.id');

        $definition = [
            'sections' => [[
                'client_id' => 'section-main',
                'title' => 'القسم الرئيسي',
                'description' => null,
                'questions' => [[
                    'client_id' => 'question-main',
                    'type' => 'radio',
                    'title' => 'اختر الإجابة',
                    'description' => null,
                    'is_required' => true,
                    'validation_rules' => [],
                    'settings' => [],
                    'options' => [
                        ['label' => 'نعم', 'value' => 'yes'],
                        ['label' => 'لا', 'value' => 'no'],
                    ],
                ]],
            ]],
            'student_fields' => [[
                'field_key' => 'basic.full_name',
                'label' => 'الاسم الكامل',
                'mode' => 'display',
                'is_required' => true,
            ]],
            'logic_rules' => [],
        ];

        $this->putJson("/api/surveys/{$surveyId}/definition", $definition)
            ->assertOk()
            ->assertJsonCount(1, 'data.sections.0.questions');

        $this->putJson("/api/surveys/{$surveyId}", [
            'description' => 'وصف محدث.',
        ])->assertOk()
            ->assertJsonPath('data.description', 'وصف محدث.');

        $publish = $this->postJson("/api/surveys/{$surveyId}/publication", [
            'status' => Survey::STATUS_PUBLISHED,
        ]);
        $publish->assertOk()
            ->assertJsonPath('data.status', Survey::STATUS_PUBLISHED);

        $survey = Survey::findOrFail($surveyId);
        $this->assertStringEndsWith('/survey/'.$survey->public_token, $publish->json('data.public_url'));
        Storage::disk('public')->assertExists($survey->logo_path);
    }

    /**
     * @return array{Survey, Student, SurveyQuestion, SurveyQuestion}
     */
    private function createPublishedSurvey(): array
    {
        $user = $this->createUser('survey-owner@example.com', '0990000001');
        $mosque = Mosque::create([
            'name' => 'مسجد الأنصار',
            'mosque_code' => 'O',
            'next_student_sequence' => 1,
        ]);
        $student = Student::create([
            'mosque_id' => $mosque->id,
            'selfnumber' => 'O-000001',
            'first_name' => 'محمد',
            'last_name' => 'الطالب',
            'username' => 'survey-student',
            'birth_date' => '2012-01-01',
            'academic_class' => 'السادس',
            'reading_level' => 'level_2',
            'father_name' => 'خالد',
            'parent_social_state' => 'married',
            'father_phone' => '0988888888',
            'password' => 'password123',
        ]);

        $survey = Survey::create([
            'name' => 'استبيان تجريبي',
            'description' => 'اختبار دورة حياة الرد.',
            'logo_path' => 'surveys/logos/test.png',
            'status' => Survey::STATUS_PUBLISHED,
            'created_by' => $user->id,
            'published_at' => now(),
        ]);
        $section = SurveySection::create([
            'survey_id' => $survey->id,
            'title' => 'القسم الأول',
            'display_order' => 0,
        ]);
        $sourceQuestion = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'section_id' => $section->id,
            'type' => 'radio',
            'title' => 'هل ترغب بالمتابعة؟',
            'is_required' => true,
            'display_order' => 0,
        ]);
        $sourceQuestion->options()->createMany([
            ['label' => 'نعم', 'value' => 'yes', 'display_order' => 0],
            ['label' => 'لا', 'value' => 'no', 'display_order' => 1],
        ]);
        $followUpQuestion = SurveyQuestion::create([
            'survey_id' => $survey->id,
            'section_id' => $section->id,
            'type' => 'short_text',
            'title' => 'اشرح السبب',
            'is_required' => false,
            'display_order' => 1,
        ]);
        $survey->studentFields()->create([
            'field_key' => 'basic.full_name',
            'label' => 'الاسم الكامل',
            'mode' => 'display',
            'is_required' => true,
            'display_order' => 0,
        ]);
        SurveyLogicRule::create([
            'survey_id' => $survey->id,
            'source_question_id' => $sourceQuestion->id,
            'target_type' => 'question',
            'target_question_id' => $followUpQuestion->id,
            'action' => 'show_if',
            'operator' => 'contains',
            'values' => ['yes'],
        ]);
        SurveyLogicRule::create([
            'survey_id' => $survey->id,
            'source_question_id' => $sourceQuestion->id,
            'target_type' => 'question',
            'target_question_id' => $followUpQuestion->id,
            'action' => 'require_if',
            'operator' => 'contains',
            'values' => ['yes'],
        ]);

        return [$survey, $student, $sourceQuestion, $followUpQuestion];
    }

    private function createUser(string $email, string $phone): User
    {
        return User::create([
            'first_name' => 'مالك',
            'last_name' => 'الاستبيان',
            'birth_date' => '1990-01-01',
            'phone' => $phone,
            'email' => $email,
            'password' => 'password123',
        ]);
    }
}
