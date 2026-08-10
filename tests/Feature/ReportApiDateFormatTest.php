<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseDate;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\User;
use App\Services\CourseDateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * يحرس صيغة التاريخ في واجهات التقارير التي يستهلكها نظام الحضور الخارجي:
 * pluck يطبّق الـ cast فيعيد كائنات Carbon لا نصوصًا، وترميزها إلى JSON
 * ينتج طابعًا زمنيًا كاملًا يرفضه المستهلك الذي يشترط YYYY-MM-DD.
 */
class ReportApiDateFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_days_are_returned_as_plain_dates(): void
    {
        $context = $this->context();
        foreach (['2026-08-10', '2026-08-11', '2026-08-12'] as $date) {
            CourseDate::create([
                'course_id' => $context['course']->id,
                'session_date' => $date,
                'status' => 'held',
                'counts_for_attendance' => true,
            ]);
        }

        $response = $this->actingAs($context['user'])
            ->getJson('/api/courses-students')
            ->assertOk();

        $courseDays = $response->json('data.0.course_date.course_days');

        $this->assertSame(['2026-08-10', '2026-08-11', '2026-08-12'], $courseDays);
        foreach ($courseDays as $day) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $day);
            $this->assertStringNotContainsString('T', $day);
        }
    }

    public function test_regenerating_course_dates_skips_the_ones_already_stored(): void
    {
        $context = $this->context();
        $payload = ['days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']];

        app(CourseDateService::class)->generateCourseDates($context['course']->id, $payload);
        $firstRunCount = CourseDate::where('course_id', $context['course']->id)->count();
        $this->assertGreaterThan(0, $firstRunCount);

        // التشغيل الثاني كان يعيد إدراج كل التواريخ لأن قائمة الموجود تُقلب فارغة.
        $second = app(CourseDateService::class)->generateCourseDates($context['course']->id, $payload);

        $this->assertNull($second);
        $this->assertSame(
            $firstRunCount,
            CourseDate::where('course_id', $context['course']->id)->count()
        );
    }

    private function context(): array
    {
        $user = User::create([
            'first_name' => 'مستخدم',
            'last_name' => 'التقارير',
            'email' => 'report-format@example.com',
            'phone' => '0991000201',
            'birth_date' => '1990-01-01',
            'password' => 'password123',
        ]);
        $mosque = Mosque::create(['name' => 'مسجد التقارير', 'mosque_code' => 'RPT01']);
        $project = Project::create([
            'name' => 'مشروع التقارير',
            'description' => 'اختبار صيغة التاريخ.',
            'audience' => 'الطلاب',
            'supervisor' => $user->id,
            'is_active' => true,
        ]);
        $course = Course::create([
            'name' => 'مقرر التقارير',
            'description' => 'مقرر',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'supervisor_id' => $user->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-16',
            'is_active' => true,
        ]);

        return compact('user', 'mosque', 'project', 'course');
    }
}
