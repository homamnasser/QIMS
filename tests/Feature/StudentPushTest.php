<?php

namespace Tests\Feature;

use App\Enums\RoleFamily;
use App\Models\Circle;
use App\Models\Course;
use App\Models\DeviceToken;
use App\Models\Memorization;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\ReadingImprovement;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentCircle;
use App\Models\StudentCourseAbsence;
use App\Models\StudentNotification;
use App\Models\User;
use App\Models\Warning;
use App\Support\StudentPushEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StudentPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.fcm.credentials', $this->fakeCredentialsPath());
    }

    /**
     * Http::fake يدمج المرشّحات ويفوز أولها، فلو ثُبّت رد ناجح في setUp لأصبح
     * تثبيت 404 داخل اختبار بلا أثر. لذلك يعلن كل اختبار ردّه بنفسه.
     */
    private function fakeFcm(int $status = 200): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-access-token']),
            'fcm.googleapis.com/*' => $status === 200
                ? Http::response(['name' => 'projects/x/messages/1'])
                : Http::response(['error' => 'UNREGISTERED'], $status),
        ]);
    }

    public function test_creating_a_warning_pushes_to_the_students_device(): void
    {
        $this->fakeFcm();
        $student = $this->student();
        $this->registerDevice($student, 'device-abc');

        Warning::create([
            'student' => $student->id,
            'warner' => $this->staff()->id,
            'title' => 'تأخر متكرر',
            'description' => 'ثلاث مرات هذا الأسبوع',
            'deduction_points' => 1,
        ]);

        $this->flushAfterResponse();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'messages:send')
            && $request['message']['token'] === 'device-abc'
            && $request['message']['notification']['title'] === 'إنذار جديد'
            && $request['message']['data']['route'] === '/warnings');
    }

    public function test_a_rejected_token_is_deleted_so_it_is_never_retried(): void
    {
        $this->fakeFcm(404);

        $student = $this->student();
        $this->registerDevice($student, 'device-dead');

        Warning::create([
            'student' => $student->id,
            'warner' => $this->staff()->id,
            'title' => 'إنذار',
            'deduction_points' => 1,
        ]);

        $this->flushAfterResponse();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'device-dead']);
    }

    /**
     * عمود type يحمل 'present' أيضاً: جدول الغياب سجل حضور. لو أُشعر كل صف
     * جديد لأخبرنا كل حاضر بأنه غائب — وهو الخطأ الذي يراه الطالب فوراً.
     */
    public function test_marking_a_student_present_pushes_nothing(): void
    {
        $this->fakeFcm();
        $student = $this->student();
        $this->registerDevice($student, 'device-present');

        StudentCourseAbsence::create([
            'student' => $student->id,
            'course' => $this->course()->id,
            'type' => 'present',
            'date' => '2026-08-13',
        ]);

        $this->flushAfterResponse();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'messages:send'));
    }

    public function test_recording_an_absence_deep_links_to_the_student_attendance_screen(): void
    {
        $this->fakeFcm();
        $student = $this->student();
        $this->registerDevice($student, 'device-absent');

        StudentCourseAbsence::create([
            'student' => $student->id,
            'course' => $this->course()->id,
            'type' => 'first_period',
            'date' => '2026-08-13',
        ]);

        $this->flushAfterResponse();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'messages:send')
            && $request['message']['data']['route'] === '/attendance'
            && $request['message']['notification']['body'] === 'غياب الحصة الأولى');
    }

    public function test_a_student_reads_their_own_attendance_including_present_rows(): void
    {
        $this->fakeFcm();
        $student = $this->student(capabilities: ['attendance']);
        $course = $this->course();

        StudentCourseAbsence::create([
            'student' => $student->id,
            'course' => $course->id,
            'type' => 'present',
            'date' => '2026-08-11',
        ]);
        StudentCourseAbsence::create([
            'student' => $student->id,
            'course' => $course->id,
            'type' => 'full',
            'is_excused' => true,
            'date' => '2026-08-12',
        ]);
        StudentCourseAbsence::create([
            'student' => $this->student(username: 'other-student')->id,
            'course' => $course->id,
            'type' => 'full',
            'date' => '2026-08-12',
        ]);

        $token = $student->createToken(
            'attendance-test-device',
            [config('auth_tokens.mobile.abilities.access')],
            now()->addHour()
        );

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/mobile/student/me/attendance')
            ->assertOk();

        // صفَّا هذا الطالب وحده، والأحدث أولاً.
        $response->assertJsonCount(2, 'data');
        $this->assertSame('full', $response->json('data.0.type'));
        $this->assertTrue($response->json('data.0.is_excused'));
        $this->assertSame('present', $response->json('data.1.type'));
    }

    /**
     * تصحيح سجل حضور قائم كان لا يُشعر إطلاقاً: الموديل كان في ON_CREATE وحده.
     */
    public function test_correcting_an_attendance_row_notifies_the_student(): void
    {
        $this->fakeFcm();
        $student = $this->student();
        $this->registerDevice($student, 'device-fix');

        $row = StudentCourseAbsence::create([
            'student' => $student->id,
            'course' => $this->course()->id,
            'type' => 'present',
            'date' => '2026-08-13',
        ]);

        $this->flushAfterResponse();

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'messages:send'));

        // الحاضر تبيّن أنه غائب
        $row->update(['type' => 'full']);

        $this->flushAfterResponse();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'messages:send')
            && $r['message']['data']['route'] === '/attendance'
            && $r['message']['notification']['body'] === 'غياب يوم كامل');
    }

    /**
     * كنا نراقب final_score وهو عمود لا يكتبه نموذج الموظف، فلم يصل إشعار قط.
     */
    public function test_changing_a_reading_evaluation_type_notifies_with_a_readable_body(): void
    {
        $this->fakeFcm();
        $student = $this->student();
        $this->registerDevice($student, 'device-reading');

        $record = ReadingImprovement::create([
            'student' => $student->id,
            'course' => $this->course()->id,
            'type' => 'no_improvement',
        ]);

        $this->flushAfterResponse();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'messages:send')
            && $r['message']['notification']['body'] === 'عدم تحسن');

        $record->update(['type' => 'significant_improvement']);

        $this->flushAfterResponse();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'messages:send')
            && $r['message']['data']['route'] === '/reading-improvements'
            && $r['message']['notification']['body'] === 'تحسن معتبر');
    }

    /**
     * عشر صفحات كانت عشرة إشعارات.
     */
    public function test_a_page_range_produces_exactly_one_notification(): void
    {
        $this->fakeFcm();
        $student = $this->student();
        $this->registerDevice($student, 'device-range');
        $staff = $this->staff();
        $staff->givePermissionTo(
            Permission::findOrCreate('إنشاء تسميع', 'web')
        );

        // quran_mode افتراضه 'none' وحلقة بلا قرآن يرفضها التحقّق، والطالب يجب
        // أن يكون مسجّلاً في الحلقة فعلاً.
        $circle = Circle::create([
            'name' => 'حلقة الإشعارات',
            'teacher_id' => $staff->id,
            'course_id' => $this->course()->id,
            'quran_mode' => 'recitation',
        ]);
        StudentCircle::create([
            'student' => $student->id,
            'circle' => $circle->id,
        ]);

        $this->actingAs($staff)->postJson('/api/memorization/createMemorization', [
            'student_id' => $student->id,
            'circle_id' => $circle->id,
            'start_page' => 1,
            'end_page' => 10,
        ])->assertSuccessful();

        $sends = 0;
        Http::assertSent(function ($r) use (&$sends) {
            if (str_contains($r->url(), 'messages:send')) {
                $sends++;
            }

            return true;
        });

        $this->assertSame(1, $sends, 'نطاق من عشر صفحات يجب أن يعطي إشعاراً واحداً.');
        $this->assertSame(10, Memorization::where('student', $student->id)->count());

        Http::assertSent(fn ($r) => str_contains($r->url(), 'messages:send')
            && $r['message']['notification']['body'] === 'الصفحات 1 - 10 (10 صفحات)');
    }

    /**
     * بلا أيقونة صريحة يرسم أندرويد أيقونة التطبيق مربعاً رمادياً، فتتشابه كل
     * الإشعارات في شريط الحالة.
     */
    public function test_each_destination_carries_its_own_tray_icon_and_channel(): void
    {
        $this->fakeFcm();
        $student = $this->student();
        $this->registerDevice($student, 'device-icon');

        Warning::create([
            'student' => $student->id,
            'warner' => $this->staff()->id,
            'title' => 'إنذار',
            'deduction_points' => 1,
        ]);

        $this->flushAfterResponse();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'messages:send')
            && $r['message']['android']['notification']['icon'] === 'ic_notif_warning'
            && $r['message']['android']['notification']['channel_id'] === 'warnings'
            && $r['message']['android']['priority'] === 'high');

        // وجهة لا يعرفها الجدول تسقط على الافتراضي لا على لا شيء — وقناةٌ فارغة
        // تعني إشعاراً يسقطه أندرويد بصمت.
        $this->assertSame(
            ['icon' => 'ic_notif_default', 'channel_id' => 'general'],
            StudentPushEvents::androidNotification('/route-from-a-newer-server'),
        );
        $this->assertSame(
            ['icon' => 'ic_notif_attendance', 'channel_id' => 'attendance'],
            StudentPushEvents::androidNotification('/attendance'),
        );
        // التسميع يشترك مع السبر والملاحظات في قناة واحدة يكتمها الطالب وحدها.
        $this->assertSame(
            'learning',
            StudentPushEvents::androidNotification('/memorization')['channel_id'],
        );
    }

    /**
     * الحالة التي وُجد الصندوق لأجلها: لا جهاز مسجّل إطلاقاً، فلا إشعار حيّ
     * يصل — ومع ذلك يبقى للطالب سجل يقرأه حين يفتح التطبيق.
     */
    public function test_the_inbox_records_a_notification_even_with_no_registered_device(): void
    {
        $this->fakeFcm();
        $student = $this->student();

        Warning::create([
            'student' => $student->id,
            'warner' => $this->staff()->id,
            'title' => 'تأخر متكرر',
            'deduction_points' => 1,
        ]);

        $this->flushAfterResponse();

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'messages:send'));
        $this->assertDatabaseHas('student_notifications', [
            'student_id' => $student->id,
            'title' => 'إنذار جديد',
            'body' => 'تأخر متكرر',
            'route' => '/warnings',
            'read_at' => null,
        ]);
    }

    public function test_a_student_reads_and_clears_their_own_inbox(): void
    {
        $student = $this->student(capabilities: ['notifications']);
        $other = $this->student(username: 'other-inbox-student');

        StudentNotification::create([
            'student_id' => $student->id,
            'title' => 'ملاحظة جديدة',
            'body' => 'الأقدم',
            'route' => '/notes',
        ]);
        StudentNotification::create([
            'student_id' => $student->id,
            'title' => 'إنذار جديد',
            'body' => 'الأحدث',
            'route' => '/warnings',
        ]);
        StudentNotification::create([
            'student_id' => $other->id,
            'title' => 'ليس له',
            'body' => 'إشعار طالب آخر',
        ]);

        $token = $student->createToken(
            'inbox-test-device',
            [config('auth_tokens.mobile.abilities.access')],
            now()->addHour()
        );

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/mobile/student/me/notifications')
            ->assertOk();

        // صفّاه وحدهما، والأحدث أولاً، وكلاهما غير مقروء.
        $response->assertJsonCount(2, 'data');
        $this->assertSame('إنذار جديد', $response->json('data.0.title'));
        $this->assertFalse($response->json('data.0.is_read'));

        $this->withToken($token->plainTextToken)
            ->postJson('/api/mobile/student/me/notifications/read')
            ->assertOk()
            ->assertJsonPath('data.marked', 2);

        $this->assertTrue(
            $this->withToken($token->plainTextToken)
                ->getJson('/api/mobile/student/me/notifications')
                ->json('data.0.is_read')
        );
        // تعليم القراءة محصور بصاحبه.
        $this->assertDatabaseHas('student_notifications', [
            'student_id' => $other->id,
            'read_at' => null,
        ]);
    }

    /**
     * صفّ الصندوق يُكتب في عملية الطلب لا داخل النداء المؤجَّل: السجل الدائم لا
     * يجوز أن يعتمد على تنفيذ ما بعد الاستجابة. هذا الاختبار **لا** يُفرّغ
     * النداءات عمداً — لا إرسال جرى، والصف موجود.
     */
    public function test_the_inbox_row_is_written_before_the_deferred_send(): void
    {
        $this->fakeFcm();
        $student = $this->student();
        $this->registerDevice($student, 'device-deferred');

        Warning::create([
            'student' => $student->id,
            'warner' => $this->staff()->id,
            'title' => 'تأخر متكرر',
            'deduction_points' => 1,
        ]);

        Http::assertNothingSent();
        $this->assertDatabaseHas('student_notifications', [
            'student_id' => $student->id,
            'title' => 'إنذار جديد',
            'route' => '/warnings',
        ]);
    }

    /**
     * afterResponse() يُسجّل ردّاً على terminate، ولا تصل إليه الاختبارات التي
     * تكتب عبر الموديل مباشرة بلا طلب HTTP، فنُنهي التطبيق يدوياً.
     */
    private function flushAfterResponse(): void
    {
        $this->app->terminate();
    }

    /**
     * @param  list<string>  $capabilities  مفاتيح من roles.student_capabilities
     */
    private function student(
        string $username = 'push-student',
        array $capabilities = [],
    ): Student {
        $role = Role::firstOrCreate(
            ['name' => 'push-student-role', 'guard_name' => 'web'],
            [
                'role_family' => RoleFamily::Student->value,
                'is_system' => false,
                'role_family_reviewed_at' => now(),
            ],
        );

        foreach ($capabilities as $capability) {
            $role->givePermissionTo(
                Permission::findOrCreate(
                    config("roles.student_capabilities.{$capability}"),
                    'web'
                )
            );
        }

        $student = Student::create([
            'mosque_id' => $this->mosque()->id,
            'first_name' => 'طالب',
            'last_name' => 'الإشعارات',
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

    private function registerDevice(Student $student, string $token): void
    {
        DeviceToken::create([
            'tokenable_type' => Student::class,
            'tokenable_id' => $student->id,
            'token' => $token,
        ]);
    }

    private function mosque(): Mosque
    {
        return Mosque::firstOrCreate(
            ['name' => 'مسجد الإشعارات'],
            ['location' => 'دمشق', 'phone' => '0911111111'],
        );
    }

    private function course(): Course
    {
        $staff = $this->staff();
        $project = Project::firstOrCreate(
            ['name' => 'مشروع الإشعارات'],
            [
                'description' => 'مشروع لاختبار إشعارات الطالب.',
                'audience' => 'الطلاب',
                'supervisor' => $staff->id,
                'is_active' => true,
            ],
        );

        return Course::firstOrCreate(
            ['name' => 'كورس الإشعارات'],
            [
                'description' => 'كورس لاختبار إشعارات الطالب.',
                'mosque_id' => $this->mosque()->id,
                'project_id' => $project->id,
                'supervisor_id' => $staff->id,
                'start_date' => '2026-08-01',
                'end_date' => '2026-12-01',
                'is_active' => true,
            ],
        );
    }

    private function staff(): User
    {
        return User::firstOrCreate(
            ['email' => 'push-staff@example.com'],
            [
                'first_name' => 'موظف',
                'last_name' => 'الإشعارات',
                'phone' => '0999999911',
                'birth_date' => '1990-01-01',
                'password' => 'password123',
            ],
        );
    }

    /**
     * حساب خدمة وهمي بمفتاح RSA حقيقي: openssl_sign يوقّع فعلاً، فنغطي مسار
     * التوقيع كاملاً بلا اعتماد حقيقي في المستودع.
     */
    private function fakeCredentialsPath(): string
    {
        $path = storage_path('framework/testing/fcm-service-account.json');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($key, $privateKey);

        file_put_contents($path, json_encode([
            'project_id' => 'alansar-test',
            'client_email' => 'fcm@alansar-test.iam.gserviceaccount.com',
            'private_key' => $privateKey,
        ]));

        return $path;
    }
}
