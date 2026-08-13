<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\Lesson;
use App\Models\Mosque;
use App\Models\Project;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * محرّك التقارير يبني كل شيء من `config/reports.php`: الكيانات المتاحة،
 * والحقول، والتصفية، والتصدير. هذا الملف يثبّت العقد الذي تعتمده الواجهة
 * وحدود الصلاحيات التي تحكمه.
 */
class ReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_lists_only_the_entities_the_user_may_read(): void
    {
        $user = $this->staff(['عرض التقارير', 'عرض كافة الطلاب']);

        $keys = $this->actingAs($user, 'web')
            ->getJson('/api/reports')
            ->assertOk()
            ->json('data.*.key');

        $this->assertSame(['students'], $keys);
    }

    public function test_reports_module_requires_its_own_permission(): void
    {
        $user = $this->staff(['عرض كافة الطلاب']);

        $this->actingAs($user, 'web')
            ->getJson('/api/reports')
            ->assertForbidden();
    }

    public function test_entity_permission_is_enforced_per_report(): void
    {
        $user = $this->staff(['عرض التقارير', 'عرض كافة الطلاب']);

        $this->actingAs($user, 'web')
            ->getJson('/api/reports/staff')
            ->assertForbidden();

        $this->actingAs($user, 'web')
            ->getJson('/api/reports/unknown-entity')
            ->assertNotFound();
    }

    public function test_rows_are_formatted_and_filtered_on_the_server(): void
    {
        $this->students();
        $user = $this->staff(['عرض التقارير', 'عرض كافة الطلاب']);

        $response = $this->actingAs($user, 'web')
            ->getJson('/api/reports/students?'.http_build_query([
                'fields' => ['first_name', 'reading_level', 'academic_class'],
                'filters' => ['reading_level' => 'level_2'],
            ]))
            ->assertOk();

        $this->assertSame(
            ['first_name', 'reading_level', 'academic_class'],
            $response->json('data.columns.*.key')
        );
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame(
            [['first_name' => 'سامي', 'reading_level' => 'المستوى الثاني', 'academic_class' => 'السابع']],
            $response->json('data.rows')
        );

        // الحقل غير المعرَّف يُهمَل بدل أن يمرّ إلى الاستعلام.
        $columns = $this->actingAs($user, 'web')
            ->getJson('/api/reports/students?fields[]=first_name&fields[]=password')
            ->assertOk()
            ->json('data.columns.*.key');

        $this->assertSame(['first_name'], $columns);
    }

    public function test_search_spans_the_configured_paths(): void
    {
        $this->students();
        $user = $this->staff(['عرض التقارير', 'عرض كافة الطلاب']);

        $names = $this->actingAs($user, 'web')
            ->getJson('/api/reports/students?q=سام')
            ->assertOk()
            ->json('data.rows.*.first_name');

        $this->assertSame(['سامي'], $names);
    }

    public function test_relation_fields_render_lists_and_counts(): void
    {
        $user = $this->staff(['عرض التقارير', 'عرض كافة الكورسات', 'عرض كافة الحلقات']);
        $context = $this->curriculum($user);

        $row = $this->actingAs($user, 'web')
            ->getJson('/api/reports/course_dates_lessons?'.http_build_query([
                'fields' => ['course_name', 'course_days', 'assigned_lessons'],
            ]))
            ->assertOk()
            ->json('data.rows.0');

        $this->assertSame('دورة الصيف', $row['course_name']);
        $this->assertSame('الإثنين 2026-08-10 | الثلاثاء 2026-08-11', $row['course_days']);
        $this->assertSame(
            "الإثنين 2026-08-10: التجويد، النون الساكنة\nالثلاثاء 2026-08-11: -",
            $row['assigned_lessons']
        );

        Circle::create([
            'name' => 'حلقة الفجر',
            'course_id' => $context['course']->id,
            'teacher_id' => $user->id,
        ]);

        $circles = $this->actingAs($user, 'web')
            ->getJson('/api/reports/circles?fields[]=name&fields[]=students_count')
            ->assertOk()
            ->json('data.rows.0');

        $this->assertSame(['name' => 'حلقة الفجر', 'students_count' => '0'], $circles);
    }

    public function test_export_defaults_to_a_right_to_left_xlsx_workbook(): void
    {
        $this->students();
        $user = $this->staff(['عرض التقارير', 'عرض كافة الطلاب']);

        $response = $this->actingAs($user, 'web')
            ->get('/api/reports/students/export?'.http_build_query([
                'fields' => ['first_name', 'reading_level'],
                'filters' => ['reading_level' => 'level_2'],
            ]))
            ->assertOk();

        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())
            ->getActiveSheet();

        $this->assertTrue($sheet->getRightToLeft());
        $this->assertSame(
            [
                ['الاسم الأول', 'مستوى القراءة'],
                ['سامي', 'المستوى الثاني'],
            ],
            $sheet->toArray()
        );
    }

    public function test_xlsx_export_keeps_numbers_numeric_and_defuses_formulas(): void
    {
        $user = $this->staff(['عرض التقارير', 'عرض كافة الطلاب', 'عرض كافة المواد']);
        $this->curriculum($user);

        Student::create([
            'first_name' => '=HYPERLINK("http://x")',
            'last_name' => 'الطالب',
            'username' => 'formula-student',
            'birth_date' => '2012-01-01',
            'academic_class' => 'السادس',
            'reading_level' => 'level_1',
            'father_name' => 'خالد',
            'parent_social_state' => 'married',
            'father_phone' => '0988888888',
            'password' => 'password123',
        ]);

        $subjects = IOFactory::load(
            $this->actingAs($user, 'web')
                ->get('/api/reports/subjects/export?fields[]=name&fields[]=min_marks&fields[]=shared_subject')
                ->assertOk()
                ->baseResponse->getFile()->getPathname()
        )->getActiveSheet();

        // الرقم يبقى رقماً لا نصاً، والحقل الفارغ يبقى «-» بلا علامة اقتباس.
        $this->assertSame(50, $subjects->getCell('B2')->getValue());
        $this->assertSame('-', $subjects->getCell('C2')->getValue());

        $students = IOFactory::load(
            $this->actingAs($user, 'web')
                ->get('/api/reports/students/export?fields[]=first_name')
                ->assertOk()
                ->baseResponse->getFile()->getPathname()
        )->getActiveSheet();

        $cell = $students->getCell('A2');
        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
        $this->assertStringContainsString('=HYPERLINK', (string) $cell->getValue());
    }

    public function test_export_streams_a_utf8_csv_of_the_filtered_rows(): void
    {
        $this->students();
        $user = $this->staff(['عرض التقارير', 'عرض كافة الطلاب']);

        $response = $this->actingAs($user, 'web')
            ->get('/api/reports/students/export?'.http_build_query([
                'format' => 'csv',
                'fields' => ['first_name', 'reading_level'],
                'filters' => ['reading_level' => 'level_2'],
            ]))
            ->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame(
            ["\xEF\xBB\xBF\"الاسم الأول\",\"مستوى القراءة\"", 'سامي,"المستوى الثاني"'],
            array_values(array_filter(explode("\n", str_replace("\r", '', $csv))))
        );
    }

    public function test_export_neutralizes_spreadsheet_formulas(): void
    {
        Student::create([
            'first_name' => '=HYPERLINK("http://x")',
            'last_name' => 'الطالب',
            'username' => 'formula-student',
            'birth_date' => '2012-01-01',
            'academic_class' => 'السادس',
            'reading_level' => 'level_1',
            'father_name' => 'خالد',
            'parent_social_state' => 'married',
            'father_phone' => '0988888888',
            'password' => 'password123',
        ]);
        $user = $this->staff(['عرض التقارير', 'عرض كافة الطلاب']);

        $csv = $this->actingAs($user, 'web')
            ->get('/api/reports/students/export?format=csv&fields[]=first_name&fields[]=phone_number')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('"\'=HYPERLINK(""http://x"")"', $csv);

        // «-» علامة القيمة الفارغة لا صيغة، فلا تُسبق بعلامة اقتباس.
        $this->assertStringContainsString(',-', $csv);
        $this->assertStringNotContainsString(",'-", $csv);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function staff(array $permissions): User
    {
        $user = User::create([
            'first_name' => 'ليلى',
            'last_name' => 'التقارير',
            'birth_date' => '1990-01-01',
            'phone' => '0990000001',
            'email' => 'reports@example.com',
            'password' => 'password123',
        ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function students(): void
    {
        Student::create([
            'first_name' => 'سامي',
            'last_name' => 'العلي',
            'username' => 'sami-student',
            'birth_date' => '2012-01-01',
            'academic_class' => 'السابع',
            'reading_level' => 'level_2',
            'father_name' => 'خالد',
            'parent_social_state' => 'married',
            'father_phone' => '0988888888',
            'password' => 'password123',
        ]);

        Student::create([
            'first_name' => 'هدى',
            'last_name' => 'الحسن',
            'username' => 'huda-student',
            'birth_date' => '2013-01-01',
            'academic_class' => 'السادس',
            'reading_level' => 'level_1',
            'father_name' => 'حسن',
            'parent_social_state' => 'divorced',
            'father_phone' => '0977777777',
            'password' => 'password123',
        ]);
    }

    /**
     * @return array{course: Course}
     */
    private function curriculum(User $supervisor): array
    {
        $mosque = Mosque::create(['name' => 'مسجد الأنصار']);
        $project = Project::create([
            'name' => 'مشروع الصيف',
            'description' => 'مشروع تجريبي.',
            'audience' => 'الفتيان',
            'supervisor' => $supervisor->id,
            'is_active' => true,
        ]);

        $course = Course::create([
            'name' => 'دورة الصيف',
            'description' => 'دورة تجريبية.',
            'mosque_id' => $mosque->id,
            'project_id' => $project->id,
            'supervisor_id' => $supervisor->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-20',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'التجويد',
            'min_marks' => 50,
            'max_marks' => 100,
            'course_id' => $course->id,
        ]);

        $first = CourseDate::create([
            'course_id' => $course->id,
            'session_date' => '2026-08-10',
            'status' => 'held',
            'counts_for_attendance' => true,
        ]);

        CourseDate::create([
            'course_id' => $course->id,
            'session_date' => '2026-08-11',
            'status' => 'held',
            'counts_for_attendance' => true,
        ]);

        $first->lessons()->sync([
            Lesson::create(['name' => 'التجويد', 'subject_id' => $subject->id])->id,
            Lesson::create(['name' => 'النون الساكنة', 'subject_id' => $subject->id])->id,
        ]);

        return ['course' => $course];
    }
}
