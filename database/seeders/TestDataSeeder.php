<?php

namespace Database\Seeders;

use App\Enums\RoleFamily;
use App\Models\Circle;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Memorization;
use App\Models\Mosque;
use App\Models\Note;
use App\Models\Project;
use App\Models\Role;
use App\Models\Sabr;
use App\Models\Student;
use App\Models\StudentCircle;
use App\Models\StudentCourseAbsence;
use App\Models\StudentMark;
use App\Models\StudentSelfNumberReservation;
use App\Models\Subject;
use App\Models\User;
use App\Models\Warning;
use App\Services\StudentCircleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔄 بدء عملية زرع البيانات التجريبية...');

        // ──────────────────────────────────────────────
        //  TRUNCATE — wipe everything in safe order
        // ──────────────────────────────────────────────
        Schema::disableForeignKeyConstraints();

        foreach ([
            'certificates',
            'recognition_awards',
            'recognition_batches',
            'evaluation_criterion_results',
            'evaluation_results',
            'evaluation_runs',
            'administration_behavior_observations',
            'evaluation_exam_results',
            'teacher_period_evaluations',
            'quran_period_assessments',
            'reading_improvements',
            'sabr_part_achievements',
            'evaluation_audit_events',
            'evaluation_candidate_enrollments',
            'evaluation_candidates',
            'evaluation_cycle_courses',
            'evaluation_periods',
            'evaluation_cycles',
            'evaluation_policies',
        ] as $evaluationTable) {
            if (Schema::hasTable($evaluationTable)) {
                DB::table($evaluationTable)->truncate();
            }
        }

        StudentMark::truncate();
        StudentCourseAbsence::truncate();
        Exam::truncate();
        Warning::truncate();
        Memorization::truncate();
        Sabr::truncate();
        Note::truncate();
        StudentCircle::truncate();
        StudentSelfNumberReservation::truncate();
        Student::truncate();
        Circle::truncate();
        DB::table('course_date_lesson')->truncate();
        CourseDate::truncate();
        Lesson::truncate();
        Subject::truncate();
        Course::truncate();
        Project::truncate();
        Mosque::truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('role_has_permissions')->truncate();
        Role::truncate();
        Permission::truncate();
        User::truncate();
        DB::table('personal_access_tokens')->truncate();

        Schema::enableForeignKeyConstraints();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ══════════════════════════════════════════════
        //  1. PERMISSIONS
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء الصلاحيات...');

        $this->call(PermissionSeeder::class);

        // ══════════════════════════════════════════════
        //  2. ROLES  (6 roles with graduated permissions)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء الأدوار...');

        $reviewedAt = now();
        $superAdminRole = Role::create(['name' => 'super-admin', 'guard_name' => 'web', 'role_family' => RoleFamily::SuperAdmin->value, 'is_system' => true, 'role_family_reviewed_at' => $reviewedAt]);
        $adminRole = Role::create(['name' => 'admin',       'guard_name' => 'web', 'role_family' => RoleFamily::Admin->value, 'is_system' => true, 'role_family_reviewed_at' => $reviewedAt]);
        $supervisorRole = Role::create(['name' => 'supervisor',  'guard_name' => 'web', 'role_family' => RoleFamily::Supervisor->value, 'is_system' => true, 'role_family_reviewed_at' => $reviewedAt]);
        $fieldSupervisorRole = Role::create(['name' => 'field-supervisor', 'guard_name' => 'web', 'role_family' => RoleFamily::FieldSupervisor->value, 'is_system' => true, 'role_family_reviewed_at' => $reviewedAt]);
        $teacherRole = Role::create(['name' => 'teacher',     'guard_name' => 'web', 'role_family' => RoleFamily::Teacher->value, 'is_system' => true, 'role_family_reviewed_at' => $reviewedAt]);
        $studentRole = Role::create(['name' => 'student',     'guard_name' => 'web', 'role_family' => RoleFamily::Student->value, 'is_system' => true, 'role_family_reviewed_at' => $reviewedAt]);

        $studentSelfServicePermissions = array_values(config('roles.student_capabilities'));
        $fieldOperationsCapability = config('roles.capabilities.field_operations');
        $superAdminRole->syncPermissions(
            Permission::whereNotIn('name', $studentSelfServicePermissions)->get()
        );
        $adminRole->syncPermissions(
            Permission::whereNotIn('name', [
                ...$studentSelfServicePermissions,
                $fieldOperationsCapability,
            ])->get()
        );

        $supervisorPerms = Permission::whereNotIn('name', [
            'إنشاء موظف', 'تعديل موظف', 'حذف موظف',
            'إنشاء دور', 'تعديل الدور', 'حذف الدور',
            'عرض كافة الصلاحيات',
            'إنشاء مشروع', 'تعديل المشروع', 'حذف المشروع', 'تعديل حالة المشروع',
            'إنشاء مسجد', 'تعديل مسجد', 'حذف مسجد',
            $fieldOperationsCapability,
            ...$studentSelfServicePermissions,
        ])->get();
        $supervisorRole->syncPermissions($supervisorPerms);

        $fieldSupervisorRole->syncPermissions(
            Permission::whereIn('name', config('roles.field_supervisor_permissions'))->get()
        );

        $teacherPerms = Permission::whereIn('name', [
            'تسجيل الدخول', 'تسجيل الخروج',
            'عرض كافة الكورسات', 'عرض تفاصيل الكورس',
            'عرض كافة المواد', 'عرض تفاصيل المادة',
            'عرض كافة الدروس', 'عرض تفاصيل الدرس',
            'عرض تواريخ الكورس', 'عرض دروس التاريخ', 'عرض المنهج الدراسي للكورس',
            'عرض منهج حلقتي', 'عرض تفاصيل الحلقة', 'عرض كافة الحلقات',
            'عرض كافة الطلاب', 'عرض تفاصيل الطالب', 'عرض طلاب الحلقة',
            'إنشاء ملاحظة', 'عرض ملاحظات الطالب', 'عرض ملاحظاتي',
            'إنشاء سبر', 'عرض سبر الطالب', 'عرض سبري', 'تعديل نتيجة السبر',
            'إنشاء تسميع', 'عرض تسميع الطالب', 'عرض تسميعاتي',
            'إنشاء إنذار', 'عرض تفاصيل الإنذار', 'عرض إنذاراتي',
            'إنشاء امتحان', 'عرض تفاصيل الامتحان', 'امتحاناتي',
            ...config('roles.teacher_attendance_and_absence_permissions'),
            'إدخال تقييم المدرس', 'إدخال تقييم القرآن',
        ])->get();
        $teacherRole->syncPermissions($teacherPerms);

        $studentPerms = Permission::whereIn('name', $studentSelfServicePermissions)->get();
        $studentRole->syncPermissions($studentPerms);

        // ══════════════════════════════════════════════
        //  3. USERS  (13 staff — Arabic names, English emails, Syrian phones)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء الموظفين...');

        $usersRaw = [
            ['first_name' => 'مدير',  'last_name' => 'المعهد',  'email' => 'superadmin@gmail.com',         'phone' => '0938316303', 'birth_date' => '1985-03-15', 'role' => 'super-admin'],
            ['first_name' => 'أحمد',  'last_name' => 'محمد',    'email' => 'ahmad.mohammad@example.com',   'phone' => '0955112233', 'birth_date' => '1988-07-22', 'role' => 'admin'],
            ['first_name' => 'خالد',  'last_name' => 'حسن',    'email' => 'khaled.hassan@example.com',    'phone' => '0944223344', 'birth_date' => '1990-11-05', 'role' => 'admin'],
            ['first_name' => 'عمر',   'last_name' => 'يوسف',   'email' => 'omar.youssef@example.com',     'phone' => '0933445566', 'birth_date' => '1992-01-18', 'role' => 'supervisor'],
            ['first_name' => 'ياسر',  'last_name' => 'علي',    'email' => 'yasser.ali@example.com',       'phone' => '0966778899', 'birth_date' => '1987-09-30', 'role' => 'supervisor'],
            ['first_name' => 'محمود', 'last_name' => 'إبراهيم', 'email' => 'mahmoud.ibrahim@example.com',  'phone' => '0955667788', 'birth_date' => '1991-04-12', 'role' => 'supervisor'],
            ['first_name' => 'سامر',  'last_name' => 'خليل',   'email' => 'samer.khalil@example.com',     'phone' => '0988112233', 'birth_date' => '1993-06-25', 'role' => 'teacher'],
            ['first_name' => 'فادي',  'last_name' => 'نور',    'email' => 'fadi.nour@example.com',        'phone' => '0977334455', 'birth_date' => '1994-02-14', 'role' => 'teacher'],
            ['first_name' => 'وليد',  'last_name' => 'عباس',   'email' => 'walid.abbas@example.com',      'phone' => '0944556677', 'birth_date' => '1989-12-01', 'role' => 'teacher'],
            ['first_name' => 'طارق',  'last_name' => 'سليم',   'email' => 'tarek.salim@example.com',      'phone' => '0933889900', 'birth_date' => '1995-08-20', 'role' => 'teacher'],
            ['first_name' => 'باسل',  'last_name' => 'رشيد',   'email' => 'basel.rashid@example.com',     'phone' => '0966112244', 'birth_date' => '1991-10-07', 'role' => 'teacher'],
            ['first_name' => 'زياد',  'last_name' => 'فارس',   'email' => 'ziad.fares@example.com',       'phone' => '0955998877', 'birth_date' => '1993-05-16', 'role' => 'teacher'],
            ['first_name' => 'مروان', 'last_name' => 'الميداني', 'email' => 'field.supervisor@example.com', 'phone' => '0955001122', 'birth_date' => '1989-08-12', 'role' => 'field-supervisor'],
        ];

        $users = [];
        foreach ($usersRaw as $row) {
            $roleName = $row['role'];
            unset($row['role']);
            $row['password'] = 'password123';
            $u = User::create($row);
            $u->assignRole($roleName);
            $users[] = $u;
        }
        // Handy aliases — [0] root, [1-2] admin, [3-5] supervisor,
        // [6-11] teacher, and [12] field supervisor.

        // ══════════════════════════════════════════════
        //  4. MOSQUES  (11)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء المساجد...');

        $mosqueNames = [
            'مسجد الإيمان', 'مسجد النور', 'مسجد التقوى', 'مسجد الهداية',
            'مسجد الرحمة', 'مسجد السلام', 'مسجد الفتح', 'مسجد البشرى',
            'مسجد الصفا', 'مسجد المنار', 'مسجد الأنصار',
        ];

        $mosques = [];
        foreach ($mosqueNames as $index => $name) {
            $mosques[] = Mosque::create([
                'name' => $name,
                'mosque_code' => 'TEST'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            ]);
        }

        // ══════════════════════════════════════════════
        //  5. PROJECTS  (11 — supervisors are users with supervisor/admin roles)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء المشاريع...');

        $projectsRaw = [
            ['name' => 'مشروع إتقان القراءة',       'description' => 'مشروع يهدف إلى تحسين مهارات القراءة القرآنية لدى الطلاب المبتدئين وضبط مخارج الحروف.',  'audience' => 'طلاب المرحلة الابتدائية',         'supervisor' => $users[3]->id],
            ['name' => 'مشروع حفظ القرآن الكريم',   'description' => 'برنامج متكامل لحفظ القرآن الكريم بطريقة منهجية ومتدرجة وفق خطة زمنية واضحة.',         'audience' => 'طلاب المرحلة الإعدادية والثانوية', 'supervisor' => $users[4]->id],
            ['name' => 'مشروع تعليم التجويد',        'description' => 'مشروع متخصص في تدريس أحكام التجويد النظرية والتطبيقية بمنهجية حديثة.',                 'audience' => 'جميع الفئات العمرية',              'supervisor' => $users[5]->id],
            ['name' => 'مشروع المراجعة المكثفة',     'description' => 'برنامج مكثف لمراجعة وتثبيت الأجزاء المحفوظة لضمان الإتقان والحفظ المتين.',             'audience' => 'الحفاظ والمراجعون',                'supervisor' => $users[3]->id],
            ['name' => 'مشروع تأهيل المعلمين',       'description' => 'برنامج تأهيلي شامل لإعداد معلمين أكفاء في تدريس القرآن الكريم وعلومه.',               'audience' => 'المعلمون الجدد والمتدربون',        'supervisor' => $users[4]->id],
            ['name' => 'مشروع رعاية المتفوقين',      'description' => 'برنامج خاص للطلاب المتميزين لتطوير مهاراتهم وإعدادهم للمسابقات القرآنية.',             'audience' => 'الطلاب المتفوقون',                 'supervisor' => $users[5]->id],
            ['name' => 'مشروع الإجازة القرآنية',     'description' => 'مشروع يؤهل الطلاب المتقنين للحصول على الإجازة في قراءة حفص عن عاصم.',                  'audience' => 'حفاظ القرآن الكريم كاملاً',        'supervisor' => $users[3]->id],
            ['name' => 'مشروع البرنامج الصيفي',      'description' => 'برنامج صيفي مكثف يجمع بين الحفظ والأنشطة الترفيهية التربوية.',                          'audience' => 'طلاب المدارس خلال العطلة الصيفية', 'supervisor' => $users[4]->id],
            ['name' => 'مشروع التلاوة المتقنة',      'description' => 'مشروع يركز على تحسين أداء التلاوة والتطبيق العملي لأحكام التجويد.',                    'audience' => 'المستوى المتوسط والمتقدم',         'supervisor' => $users[5]->id],
            ['name' => 'مشروع الحلقات النموذجية',    'description' => 'مشروع لإنشاء حلقات قرآنية نموذجية تتبع أفضل الممارسات التعليمية والتقنية.',            'audience' => 'جميع المستويات',                   'supervisor' => $users[3]->id],
            ['name' => 'مشروع التحفيز والمكافآت',    'description' => 'نظام تحفيزي متكامل لتشجيع الطلاب على الحفظ والمراجعة المنتظمة وتكريم المتميزين.',      'audience' => 'جميع طلاب المعهد',                 'supervisor' => $users[4]->id],
        ];

        $projects = [];
        foreach ($projectsRaw as $d) {
            $projects[] = Project::create($d);
        }

        // ══════════════════════════════════════════════
        //  6. COURSES  (12 — 2 with parent_course_id)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء الدورات...');

        $coursesRaw = [
            ['name' => 'دورة الإتقان الأولى',      'description' => 'المستوى الأول من برنامج إتقان القراءة القرآنية.',              'mosque_id' => $mosques[0]->id, 'project_id' => $projects[0]->id, 'supervisor_id' => $users[3]->id, 'start_date' => '2026-01-15', 'end_date' => '2026-06-30'],
            ['name' => 'دورة الإتقان الثانية',      'description' => 'المستوى الثاني المتقدم من برنامج إتقان القراءة.',              'mosque_id' => $mosques[0]->id, 'project_id' => $projects[0]->id, 'supervisor_id' => $users[3]->id, 'start_date' => '2026-02-01', 'end_date' => '2026-07-31'],
            ['name' => 'دورة الحفظ للمبتدئين',      'description' => 'دورة تأسيسية لتعليم أساليب حفظ القرآن الكريم.',               'mosque_id' => $mosques[1]->id, 'project_id' => $projects[1]->id, 'supervisor_id' => $users[4]->id, 'start_date' => '2026-01-20', 'end_date' => '2026-06-20'],
            ['name' => 'دورة الحفظ للمتقدمين',      'description' => 'دورة متقدمة للطلاب المتقنين لأساسيات الحفظ.',                 'mosque_id' => $mosques[1]->id, 'project_id' => $projects[1]->id, 'supervisor_id' => $users[4]->id, 'start_date' => '2026-03-01', 'end_date' => '2026-08-31'],
            ['name' => 'دورة التجويد الأساسية',      'description' => 'دراسة أحكام التجويد الأساسية نظرياً وتطبيقياً.',              'mosque_id' => $mosques[2]->id, 'project_id' => $projects[2]->id, 'supervisor_id' => $users[5]->id, 'start_date' => '2026-02-15', 'end_date' => '2026-07-15'],
            ['name' => 'دورة المراجعة الفصلية',      'description' => 'برنامج فصلي مكثف لمراجعة وتثبيت المحفوظات.',                 'mosque_id' => $mosques[3]->id, 'project_id' => $projects[3]->id, 'supervisor_id' => $users[3]->id, 'start_date' => '2026-04-01', 'end_date' => '2026-06-30'],
            ['name' => 'دورة تأهيل المعلمين',        'description' => 'برنامج تدريبي لإعداد معلمي القرآن الكريم.',                   'mosque_id' => $mosques[4]->id, 'project_id' => $projects[4]->id, 'supervisor_id' => $users[4]->id, 'start_date' => '2026-03-15', 'end_date' => '2026-09-15'],
            ['name' => 'دورة المتفوقين',             'description' => 'برنامج خاص للطلاب المتميزين في الحفظ والتلاوة.',             'mosque_id' => $mosques[5]->id, 'project_id' => $projects[5]->id, 'supervisor_id' => $users[5]->id, 'start_date' => '2026-01-10', 'end_date' => '2026-12-10'],
            ['name' => 'دورة الإجازة القرآنية',      'description' => 'تأهيل الحفاظ للحصول على إجازة في رواية حفص عن عاصم.',         'mosque_id' => $mosques[6]->id, 'project_id' => $projects[6]->id, 'supervisor_id' => $users[3]->id, 'start_date' => '2026-01-05', 'end_date' => '2026-12-31'],
            ['name' => 'الدورة الصيفية المكثفة',     'description' => 'برنامج صيفي مكثف للحفظ والمراجعة والأنشطة.',                 'mosque_id' => $mosques[7]->id, 'project_id' => $projects[7]->id, 'supervisor_id' => $users[4]->id, 'start_date' => '2026-06-15', 'end_date' => '2026-08-31'],
            ['name' => 'دورة التلاوة المتقنة',       'description' => 'تحسين أداء التلاوة والتطبيق الصوتي لأحكام التجويد.',          'mosque_id' => $mosques[8]->id, 'project_id' => $projects[8]->id, 'supervisor_id' => $users[5]->id, 'start_date' => '2026-02-01', 'end_date' => '2026-07-31'],
            ['name' => 'دورة الحلقات النموذجية',     'description' => 'حلقات قرآنية نموذجية وفق أفضل الممارسات التعليمية.',          'mosque_id' => $mosques[9]->id, 'project_id' => $projects[9]->id, 'supervisor_id' => $users[3]->id, 'start_date' => '2026-03-01', 'end_date' => '2026-11-30'],
        ];

        $courses = [];
        foreach ($coursesRaw as $d) {
            $courses[] = Course::create($d);
        }

        // Link parent courses: course 2→1, course 4→3
        $courses[1]->update(['parent_course_id' => $courses[0]->id]);
        $courses[3]->update(['parent_course_id' => $courses[2]->id]);

        // ══════════════════════════════════════════════
        //  7. SUBJECTS  (14 — spread across courses)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء المواد الدراسية...');

        $subjectsRaw = [
            ['name' => 'أحكام النون الساكنة والتنوين', 'description' => 'دراسة الإظهار والإدغام والإقلاب والإخفاء.',  'course_id' => $courses[0]->id, 'min_marks' => 0, 'max_marks' => 100],
            ['name' => 'أحكام الميم الساكنة',          'description' => 'الإخفاء الشفوي والإدغام المتماثل والإظهار.', 'course_id' => $courses[0]->id, 'min_marks' => 0, 'max_marks' => 100],
            ['name' => 'المدود وأقسامها',              'description' => 'المد الطبيعي والفرعي وأحكامه.',               'course_id' => $courses[1]->id, 'min_marks' => 0, 'max_marks' => 100],
            ['name' => 'الوقف والابتداء',              'description' => 'قواعد الوقف والابتداء في التلاوة.',            'course_id' => $courses[1]->id, 'min_marks' => 0, 'max_marks' => 80],
            ['name' => 'حفظ جزء عمّ',                  'description' => 'حفظ الجزء الثلاثين من القرآن الكريم.',         'course_id' => $courses[2]->id, 'min_marks' => 0, 'max_marks' => 100],
            ['name' => 'حفظ جزء تبارك',                'description' => 'حفظ الجزء التاسع والعشرين.',                   'course_id' => $courses[2]->id, 'min_marks' => 0, 'max_marks' => 100],
            ['name' => 'حفظ جزء قد سمع',               'description' => 'حفظ الجزء الثامن والعشرين.',                   'course_id' => $courses[3]->id, 'min_marks' => 0, 'max_marks' => 100],
            ['name' => 'مراجعة الأجزاء السابقة',       'description' => 'مراجعة وتثبيت الأجزاء الثلاثة الأخيرة.',       'course_id' => $courses[3]->id, 'min_marks' => 0, 'max_marks' => 100],
            ['name' => 'مخارج الحروف',                 'description' => 'دراسة مخارج الحروف العربية الـ17.',             'course_id' => $courses[4]->id, 'min_marks' => 0, 'max_marks' => 100],
            ['name' => 'صفات الحروف',                  'description' => 'الصفات اللازمة والعارضة للحروف.',               'course_id' => $courses[4]->id, 'min_marks' => 0, 'max_marks' => 100],
            ['name' => 'مراجعة شاملة',                 'description' => 'مراجعة شاملة لجميع الأجزاء المحفوظة.',         'course_id' => $courses[5]->id, 'min_marks' => 0, 'max_marks' => 100],
            ['name' => 'أصول التدريس القرآني',         'description' => 'منهجيات تعليم القرآن الكريم وإدارة الحلقات.',  'course_id' => $courses[6]->id, 'min_marks' => 0, 'max_marks' => 100],
            ['name' => 'التحليل الصوتي للتلاوة',       'description' => 'تحليل الأداء الصوتي وتقنيات التلاوة.',          'course_id' => $courses[7]->id, 'min_marks' => 0, 'max_marks' => 100],
            ['name' => 'رواية حفص عن عاصم',            'description' => 'دراسة تفصيلية لأحكام رواية حفص.',              'course_id' => $courses[8]->id, 'min_marks' => 0, 'max_marks' => 100],
        ];

        $subjects = [];
        foreach ($subjectsRaw as $d) {
            $subjects[] = Subject::create($d);
        }

        // ══════════════════════════════════════════════
        //  8. LESSONS  (14 — linked to subjects)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء الدروس...');

        $lessonsRaw = [
            ['name' => 'الإظهار الحلقي',       'description' => 'حروف الإظهار الستة وأمثلتها التطبيقية.',       'start_page' => 1, 'end_page' => 5,   'subject_id' => $subjects[0]->id],
            ['name' => 'الإدغام بغنة',         'description' => 'حروف الإدغام بغنة: ي ن م و.',                  'start_page' => 6, 'end_page' => 10,  'subject_id' => $subjects[0]->id],
            ['name' => 'الإخفاء الحقيقي',      'description' => 'حروف الإخفاء الخمسة عشر.',                     'start_page' => 11, 'end_page' => 15,  'subject_id' => $subjects[0]->id],
            ['name' => 'الإخفاء الشفوي',        'description' => 'إخفاء الميم الساكنة عند حرف الباء.',            'start_page' => 1, 'end_page' => 4,   'subject_id' => $subjects[1]->id],
            ['name' => 'المد الطبيعي',          'description' => 'المد الأصلي ومقداره وأمثلته.',                  'start_page' => 1, 'end_page' => 6,   'subject_id' => $subjects[2]->id],
            ['name' => 'المد الفرعي',           'description' => 'المد بسبب الهمز والسكون.',                     'start_page' => 7, 'end_page' => 12,  'subject_id' => $subjects[2]->id],
            ['name' => 'سورة الناس والفلق',     'description' => 'حفظ وتسميع سورتي الناس والفلق.',               'start_page' => 602, 'end_page' => 604, 'subject_id' => $subjects[4]->id],
            ['name' => 'سورة الإخلاص والمسد',   'description' => 'حفظ وتسميع سورتي الإخلاص والمسد.',             'start_page' => 600, 'end_page' => 601, 'subject_id' => $subjects[4]->id],
            ['name' => 'سورة الملك',            'description' => 'حفظ وتسميع سورة الملك كاملة.',                 'start_page' => 562, 'end_page' => 564, 'subject_id' => $subjects[5]->id],
            ['name' => 'مخارج الحلق',           'description' => 'المخارج الثلاثة لحروف الحلق.',                  'start_page' => 1, 'end_page' => 5,   'subject_id' => $subjects[8]->id],
            ['name' => 'مخارج اللسان',          'description' => 'المخارج العشرة لحروف اللسان.',                  'start_page' => 6, 'end_page' => 12,  'subject_id' => $subjects[8]->id],
            ['name' => 'مخارج الشفتين',         'description' => 'مخارج حروف الشفتين والخيشوم.',                  'start_page' => 13, 'end_page' => 16,  'subject_id' => $subjects[8]->id],
            ['name' => 'المراجعة التراكمية',     'description' => 'مراجعة تراكمية شاملة لكامل المنهج.',            'start_page' => 1, 'end_page' => 20,  'subject_id' => $subjects[10]->id],
            ['name' => 'أساليب إدارة الحلقات',   'description' => 'تقنيات إدارة الوقت والتفاعل مع الطلاب.',       'start_page' => 1, 'end_page' => 10,  'subject_id' => $subjects[11]->id],
        ];

        $lessons = [];
        foreach ($lessonsRaw as $d) {
            $lessons[] = Lesson::create($d);
        }

        // ══════════════════════════════════════════════
        //  9. COURSE DATES  (3 dates for each of the first 8 courses)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء تواريخ الجلسات...');

        $courseDates = [];
        $dateOffsets = [
            0 => ['2026-01-17', '2026-01-24', '2026-01-31'],
            1 => ['2026-02-07', '2026-02-14', '2026-02-21'],
            2 => ['2026-01-24', '2026-01-31', '2026-02-07'],
            3 => ['2026-03-07', '2026-03-14', '2026-03-21'],
            4 => ['2026-02-21', '2026-02-28', '2026-03-07'],
            5 => ['2026-04-04', '2026-04-11', '2026-04-18'],
            6 => ['2026-03-21', '2026-03-28', '2026-04-04'],
            7 => ['2026-01-17', '2026-01-24', '2026-01-31'],
        ];

        foreach ($dateOffsets as $ci => $dates) {
            foreach ($dates as $dt) {
                $courseDates[] = CourseDate::create([
                    'course_id' => $courses[$ci]->id,
                    'session_date' => $dt,
                ]);
            }
        }

        // ══════════════════════════════════════════════
        //  10. COURSE_DATE_LESSON  (assign lessons to dates)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إسناد الدروس للتواريخ...');

        // courseDates[0-2] → course 0 lessons, courseDates[3-5] → course 1 lessons, etc.
        $cdlPairs = [
            [$courseDates[0]->id, $lessons[0]->id],
            [$courseDates[1]->id, $lessons[1]->id],
            [$courseDates[2]->id, $lessons[2]->id],
            [$courseDates[3]->id, $lessons[4]->id],
            [$courseDates[4]->id, $lessons[5]->id],
            [$courseDates[6]->id, $lessons[6]->id],
            [$courseDates[7]->id, $lessons[7]->id],
            [$courseDates[9]->id, $lessons[8]->id],
            [$courseDates[12]->id, $lessons[9]->id],
            [$courseDates[13]->id, $lessons[10]->id],
            [$courseDates[14]->id, $lessons[11]->id],
            [$courseDates[15]->id, $lessons[12]->id],
        ];

        foreach ($cdlPairs as [$cdId, $lId]) {
            DB::table('course_date_lesson')->insert([
                'course_date_id' => $cdId,
                'lesson_id' => $lId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ══════════════════════════════════════════════
        //  11. CIRCLES  (12 — each has a teacher, linked to a course)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء الحلقات الدراسية...');

        $circlesRaw = [
            ['name' => 'حلقة الفاتحة',   'teacher_id' => $users[6]->id,  'course_id' => $courses[0]->id, 'quran_mode' => 'talqin'],
            ['name' => 'حلقة البقرة',    'teacher_id' => $users[7]->id,  'course_id' => $courses[0]->id, 'quran_mode' => 'talqin'],
            ['name' => 'حلقة آل عمران',  'teacher_id' => $users[8]->id,  'course_id' => $courses[1]->id, 'quran_mode' => 'talqin'],
            ['name' => 'حلقة النساء',    'teacher_id' => $users[9]->id,  'course_id' => $courses[2]->id, 'quran_mode' => 'recitation'],
            ['name' => 'حلقة المائدة',   'teacher_id' => $users[10]->id, 'course_id' => $courses[2]->id, 'quran_mode' => 'recitation'],
            ['name' => 'حلقة الأنعام',   'teacher_id' => $users[11]->id, 'course_id' => $courses[3]->id, 'quran_mode' => 'recitation'],
            ['name' => 'حلقة الأعراف',   'teacher_id' => $users[6]->id,  'course_id' => $courses[4]->id, 'quran_mode' => 'talqin'],
            ['name' => 'حلقة الأنفال',   'teacher_id' => $users[7]->id,  'course_id' => $courses[5]->id, 'quran_mode' => 'recitation'],
            ['name' => 'حلقة التوبة',    'teacher_id' => $users[8]->id,  'course_id' => $courses[6]->id, 'quran_mode' => 'none'],
            ['name' => 'حلقة يونس',      'teacher_id' => $users[9]->id,  'course_id' => $courses[7]->id, 'quran_mode' => 'recitation'],
            ['name' => 'حلقة هود',       'teacher_id' => $users[10]->id, 'course_id' => $courses[8]->id, 'quran_mode' => 'recitation'],
            ['name' => 'حلقة يوسف',      'teacher_id' => $users[11]->id, 'course_id' => $courses[9]->id, 'quran_mode' => 'recitation'],
        ];

        $circles = [];
        foreach ($circlesRaw as $d) {
            $circles[] = Circle::create($d);
        }

        // ══════════════════════════════════════════════
        //  12. STUDENTS  (14 — Arabic names, Syrian phones)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء الطلاب...');

        $studentsRaw = [
            ['first_name' => 'همام',     'last_name' => 'ناصر',      'username' => 'hammam-nasser',      'phone_number' => '0982244990', 'birth_date' => '2012-05-10', 'academic_class' => 'السابع',  'reading_level' => 'level_2', 'father_name' => 'ناصر أحمد',      'parent_social_state' => 'married',  'father_phone' => '0933112200'],
            ['first_name' => 'كنان',     'last_name' => 'الحوري',    'username' => 'kenan-alhouri',      'phone_number' => '0955667711', 'birth_date' => '2011-08-22', 'academic_class' => 'الثامن',  'reading_level' => 'level_3', 'father_name' => 'سمير الحوري',    'parent_social_state' => 'married',  'father_phone' => '0944556600'],
            ['first_name' => 'حسين',     'last_name' => 'الحوري',    'username' => 'hussein-alhouri',    'phone_number' => '0977889911', 'birth_date' => '2013-02-14', 'academic_class' => 'السادس', 'reading_level' => 'level_1', 'father_name' => 'سمير الحوري',    'parent_social_state' => 'married',  'father_phone' => '0944556600'],
            ['first_name' => 'عبد الرحمن', 'last_name' => 'سالم',     'username' => 'abdalrahman-salem',  'phone_number' => '0933445522', 'birth_date' => '2010-11-03', 'academic_class' => 'التاسع',  'reading_level' => 'level_3', 'father_name' => 'سالم يوسف',      'parent_social_state' => 'married',  'father_phone' => '0966778800'],
            ['first_name' => 'يزن',      'last_name' => 'أحمد',      'username' => 'yazan-ahmad',        'phone_number' => '0944112233', 'birth_date' => '2012-07-19', 'academic_class' => 'السابع',  'reading_level' => 'level_2', 'father_name' => 'أحمد خالد',      'parent_social_state' => 'divorced', 'father_phone' => '0955223344'],
            ['first_name' => 'أنس',      'last_name' => 'خالد',      'username' => 'anas-khaled',        'phone_number' => '0966334455', 'birth_date' => '2011-01-25', 'academic_class' => 'الثامن',  'reading_level' => 'level_2', 'father_name' => 'خالد إبراهيم',   'parent_social_state' => 'married',  'father_phone' => '0977445566'],
            ['first_name' => 'بلال',     'last_name' => 'عمر',       'username' => 'bilal-omar',         'phone_number' => '0988556677', 'birth_date' => '2013-09-08', 'academic_class' => 'السادس', 'reading_level' => 'level_1', 'father_name' => 'عمر حسن',        'parent_social_state' => 'widowed',  'father_phone' => '0933667788'],
            ['first_name' => 'معاذ',     'last_name' => 'يوسف',      'username' => 'muath-youssef',      'phone_number' => '0955778899', 'birth_date' => '2010-04-17', 'academic_class' => 'التاسع',  'reading_level' => 'level_3', 'father_name' => 'يوسف محمود',     'parent_social_state' => 'married',  'father_phone' => '0944889900'],
            ['first_name' => 'أسامة',    'last_name' => 'حسن',       'username' => 'osama-hassan',       'phone_number' => '0944990011', 'birth_date' => '2012-12-30', 'academic_class' => 'السابع',  'reading_level' => 'level_2', 'father_name' => 'حسن علي',        'parent_social_state' => 'married',  'father_phone' => '0966001122'],
            ['first_name' => 'إياد',     'last_name' => 'محمد',      'username' => 'iyad-mohammad',      'phone_number' => '0977112233', 'birth_date' => '2011-06-11', 'academic_class' => 'الثامن',  'reading_level' => 'level_2', 'father_name' => 'محمد فراس',      'parent_social_state' => 'divorced', 'father_phone' => '0955334455'],
            ['first_name' => 'سيف الدين', 'last_name' => 'علي',       'username' => 'saifaldeen-ali',     'phone_number' => '0933556677', 'birth_date' => '2013-03-05', 'academic_class' => 'السادس', 'reading_level' => 'level_1', 'father_name' => 'علي سعيد',       'parent_social_state' => 'married',  'father_phone' => '0988778899'],
            ['first_name' => 'رامي',     'last_name' => 'نور الدين', 'username' => 'rami-nouraldeen',    'phone_number' => '0966778844', 'birth_date' => '2010-10-20', 'academic_class' => 'التاسع',  'reading_level' => 'level_3', 'father_name' => 'نور الدين سامر', 'parent_social_state' => 'married',  'father_phone' => '0944223355'],
            ['first_name' => 'عمار',     'last_name' => 'شاهين',     'username' => 'ammar-shaheen',      'phone_number' => '0955889944', 'birth_date' => '2012-08-14', 'academic_class' => 'السابع',  'reading_level' => 'level_2', 'father_name' => 'شاهين محمد',     'parent_social_state' => 'married',  'father_phone' => '0977556611'],
            ['first_name' => 'ليث',      'last_name' => 'حمدان',     'username' => 'laith-hamdan',       'phone_number' => '0944667722', 'birth_date' => '2011-04-02', 'academic_class' => 'الثامن',  'reading_level' => 'level_3', 'father_name' => 'حمدان فؤاد',     'parent_social_state' => 'widowed',  'father_phone' => '0933889933'],
        ];

        $students = [];
        foreach ($studentsRaw as $d) {
            $d['password'] = 'student123';
            $s = Student::create($d);
            $s->assignRole($studentRole);
            $students[] = $s;
        }

        // ══════════════════════════════════════════════
        //  13. STUDENT–CIRCLE ENROLMENT  (each student → 1 circle)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ تسجيل الطلاب في الحلقات...');

        // Distribute 14 students across 12 circles (circles 0 and 3 get 2 students each)
        // FK columns are named `student` and `circle` (bare — no _id suffix)
        $enrolments = [
            [$students[0]->id,  $circles[0]->id],   // circle 0 (course 0)
            [$students[1]->id,  $circles[0]->id],   // circle 0 (course 0)
            [$students[2]->id,  $circles[1]->id],   // circle 1 (course 0)
            [$students[3]->id,  $circles[2]->id],   // circle 2 (course 1)
            [$students[4]->id,  $circles[3]->id],   // circle 3 (course 2)
            [$students[5]->id,  $circles[3]->id],   // circle 3 (course 2)
            [$students[6]->id,  $circles[4]->id],   // circle 4 (course 2)
            [$students[7]->id,  $circles[5]->id],   // circle 5 (course 3)
            [$students[8]->id,  $circles[6]->id],   // circle 6 (course 4)
            [$students[9]->id,  $circles[7]->id],   // circle 7 (course 5)
            [$students[10]->id, $circles[8]->id],   // circle 8 (course 6)
            [$students[11]->id, $circles[9]->id],   // circle 9 (course 7)
            [$students[12]->id, $circles[10]->id],  // circle 10 (course 8)
            [$students[13]->id, $circles[11]->id],  // circle 11 (course 9)
        ];

        $studentCircleService = app(StudentCircleService::class);
        foreach ($enrolments as [$studentId, $circleId]) {
            $studentCircleService->addStudentsToCircle($circleId, [$studentId]);
        }

        // ══════════════════════════════════════════════
        //  14. NOTES  (12 — from teachers/supervisors to students)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء الملاحظات...');

        $notesRaw = [
            ['student_id' => $students[0]->id, 'user_id' => $users[6]->id,  'title' => 'تحسن ملحوظ في التلاوة',        'description' => 'أظهر الطالب تحسناً واضحاً في تطبيق أحكام التجويد خلال الأسبوعين الماضيين.'],
            ['student_id' => $students[1]->id, 'user_id' => $users[6]->id,  'title' => 'حاجة لمزيد من المراجعة',        'description' => 'يحتاج الطالب لمراجعة مكثفة للأجزاء المحفوظة لتثبيت الحفظ.'],
            ['student_id' => $students[2]->id, 'user_id' => $users[7]->id,  'title' => 'تميز في الحفظ',                 'description' => 'يحفظ الطالب بسرعة فائقة ويتميز بقوة الذاكرة ودقة التسميع.'],
            ['student_id' => $students[3]->id, 'user_id' => $users[8]->id,  'title' => 'انضباط ممتاز',                  'description' => 'الطالب ملتزم بمواعيد الحلقة ويظهر سلوكاً مثالياً.'],
            ['student_id' => $students[4]->id, 'user_id' => $users[9]->id,  'title' => 'صعوبة في مخارج الحروف',         'description' => 'يواجه الطالب صعوبة في نطق حروف الحلق ويحتاج لتدريب إضافي.'],
            ['student_id' => $students[5]->id, 'user_id' => $users[9]->id,  'title' => 'مشاركة فعالة',                  'description' => 'يشارك الطالب بفاعلية في النقاشات ويساعد زملاءه في المراجعة.'],
            ['student_id' => $students[6]->id, 'user_id' => $users[10]->id, 'title' => 'غياب متكرر',                    'description' => 'لوحظ غياب الطالب المتكرر خلال الشهر الماضي، يُرجى التواصل مع ولي الأمر.'],
            ['student_id' => $students[7]->id, 'user_id' => $users[11]->id, 'title' => 'إنجاز حفظ الجزء كاملاً',        'description' => 'أتم الطالب حفظ جزء قد سمع بتقدير ممتاز مع إتقان التجويد.'],
            ['student_id' => $students[8]->id, 'user_id' => $users[6]->id,  'title' => 'يحتاج دعم في القراءة',          'description' => 'مستوى القراءة أقل من المتوقع، يُنصح بجلسات تقوية أسبوعية.'],
            ['student_id' => $students[9]->id, 'user_id' => $users[3]->id,  'title' => 'ترشيح للمسابقات',               'description' => 'مرشح للمشاركة في مسابقة حفظ القرآن الكريم على مستوى المحافظة.'],
            ['student_id' => $students[10]->id, 'user_id' => $users[4]->id,  'title' => 'سلوك يحتاج متابعة',             'description' => 'لوحظ بعض التصرفات غير اللائقة أثناء الحلقة تحتاج متابعة.'],
            ['student_id' => $students[11]->id, 'user_id' => $users[5]->id,  'title' => 'تقدم ملموس في التجويد',         'description' => 'تحسن أداء الطالب بشكل ملحوظ بعد حضوره جلسات التجويد الإضافية.'],
        ];

        foreach ($notesRaw as $d) {
            Note::create($d);
        }

        // ══════════════════════════════════════════════
        //  15. SABRS  (12 — student must be in circle of that course)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء سجلات السبر...');

        // FK columns: student, giver, course (bare names)
        $sabrsRaw = [
            ['student' => $students[0]->id, 'giver' => $users[6]->id,  'course' => $courses[0]->id, 'value' => 'ممتاز',    'type' => 'داخلي', 'date' => '2026-02-01', 'parts' => [30],       'note' => 'أداء متميز في سبر جزء عمّ.'],
            ['student' => $students[1]->id, 'giver' => $users[6]->id,  'course' => $courses[0]->id, 'value' => 'جيد جداً', 'type' => 'داخلي', 'date' => '2026-02-05', 'parts' => [29, 30],    'note' => 'بحاجة لتثبيت أكثر في جزء تبارك.'],
            ['student' => $students[2]->id, 'giver' => $users[7]->id,  'course' => $courses[0]->id, 'value' => 'جيد',      'type' => 'أوقاف', 'date' => '2026-02-10', 'parts' => [28],       'note' => null],
            ['student' => $students[3]->id, 'giver' => $users[8]->id,  'course' => $courses[1]->id, 'value' => 'ممتاز',    'type' => 'أوقاف', 'date' => '2026-03-01', 'parts' => [1, 2],      'note' => 'سبر أوقاف ناجح بجدارة.'],
            ['student' => $students[4]->id, 'giver' => $users[9]->id,  'course' => $courses[2]->id, 'value' => 'جيد',      'type' => 'داخلي', 'date' => '2026-02-15', 'parts' => [30],       'note' => 'يحتاج مراجعة إضافية.'],
            ['student' => $students[5]->id, 'giver' => $users[9]->id,  'course' => $courses[2]->id, 'value' => 'جيد جداً', 'type' => 'داخلي', 'date' => '2026-02-20', 'parts' => [29],       'note' => null],
            ['student' => $students[6]->id, 'giver' => $users[10]->id, 'course' => $courses[2]->id, 'value' => 'مقبول',    'type' => 'أوقاف', 'date' => '2026-03-05', 'parts' => [30],       'note' => 'أداء متوسط يحتاج تحسين.'],
            ['student' => $students[7]->id, 'giver' => $users[11]->id, 'course' => $courses[3]->id, 'value' => 'ممتاز',    'type' => 'داخلي', 'date' => '2026-03-15', 'parts' => [27, 28, 29], 'note' => 'حفظ متين وأداء رائع.'],
            ['student' => $students[8]->id, 'giver' => $users[6]->id,  'course' => $courses[4]->id, 'value' => 'جيد',      'type' => 'داخلي', 'date' => '2026-03-10', 'parts' => [1],        'note' => null],
            ['student' => $students[9]->id, 'giver' => $users[7]->id,  'course' => $courses[5]->id, 'value' => 'جيد جداً', 'type' => 'أوقاف', 'date' => '2026-04-10', 'parts' => [5, 6],      'note' => 'مراجعة جيدة مع بعض الهنات.'],
            ['student' => $students[10]->id, 'giver' => $users[8]->id,  'course' => $courses[6]->id, 'value' => 'ممتاز',    'type' => 'داخلي', 'date' => '2026-04-01', 'parts' => [10],       'note' => null],
            ['student' => $students[11]->id, 'giver' => $users[9]->id,  'course' => $courses[7]->id, 'value' => 'جيد',      'type' => 'أوقاف', 'date' => '2026-02-01', 'parts' => [15, 16],    'note' => 'بحاجة لمتابعة أكثر.'],
        ];

        foreach ($sabrsRaw as $d) {
            Sabr::create($d);
        }

        // ══════════════════════════════════════════════
        //  16. MEMORIZATIONS  (14 — linked to circle, course and attendance day)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء سجلات التسميع...');

        // FK columns: student, giver (bare names)
        $memRaw = [
            ['student' => $students[0]->id, 'giver' => $users[6]->id,  'page_number' => 604, 'name' => 'سورة الناس'],
            ['student' => $students[0]->id, 'giver' => $users[6]->id,  'page_number' => 603, 'name' => 'سورة الفلق'],
            ['student' => $students[1]->id, 'giver' => $users[6]->id,  'page_number' => 604, 'name' => 'سورة الناس'],
            ['student' => $students[1]->id, 'giver' => $users[6]->id,  'page_number' => 602, 'name' => 'سورة الإخلاص'],
            ['student' => $students[2]->id, 'giver' => $users[7]->id,  'page_number' => 601, 'name' => 'سورة المسد'],
            ['student' => $students[3]->id, 'giver' => $users[8]->id,  'page_number' => 600, 'name' => 'سورة النصر'],
            ['student' => $students[4]->id, 'giver' => $users[9]->id,  'page_number' => 599, 'name' => 'سورة الكافرون'],
            ['student' => $students[5]->id, 'giver' => $users[9]->id,  'page_number' => 598, 'name' => 'سورة الكوثر'],
            ['student' => $students[6]->id, 'giver' => $users[10]->id, 'page_number' => 597, 'name' => 'سورة الماعون'],
            ['student' => $students[7]->id, 'giver' => $users[11]->id, 'page_number' => 596, 'name' => 'سورة قريش'],
            ['student' => $students[8]->id, 'giver' => $users[6]->id,  'page_number' => 595, 'name' => 'سورة الفيل'],
            ['student' => $students[9]->id, 'giver' => $users[7]->id,  'page_number' => 594, 'name' => 'سورة الهمزة'],
            ['student' => $students[10]->id, 'giver' => $users[8]->id,  'page_number' => 593, 'name' => 'سورة العصر'],
            ['student' => $students[11]->id, 'giver' => $users[9]->id,  'page_number' => 592, 'name' => 'سورة التكاثر'],
        ];

        $memorizationCircleIndexes = [0, 0, 0, 0, 1, 2, 3, 3, 4, 5, 6, 7, 8, 9];
        foreach ($memRaw as $index => $d) {
            $circle = $circles[$memorizationCircleIndexes[$index]];
            $courseDate = collect($courseDates)
                ->first(fn ($date) => $date->course_id === $circle->course_id);
            $d['course_id'] = $circle->course_id;
            $d['circle_id'] = $circle->id;
            $d['course_date_id'] = $courseDate?->id;
            $d['record_type'] = 'memorization';
            $d['recorded_at'] = $courseDate?->session_date?->startOfDay() ?? now();
            Memorization::create($d);
        }

        // ══════════════════════════════════════════════
        //  17. WARNINGS  (12)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء الإنذارات...');

        // FK columns: student, warner (bare names)
        $warningsRaw = [
            ['student' => $students[6]->id,  'warner' => $users[10]->id, 'title' => 'إنذار أول - غياب متكرر',              'description' => 'تغيب الطالب 3 مرات متتالية دون عذر مقبول.'],
            ['student' => $students[4]->id,  'warner' => $users[9]->id,  'title' => 'إنذار - عدم إحضار المصحف',            'description' => 'لم يحضر الطالب مصحفه لثلاث حصص متتالية.'],
            ['student' => $students[0]->id,  'warner' => $users[6]->id,  'title' => 'تنبيه - تأخر عن موعد الحلقة',         'description' => 'تأخر الطالب عن الحضور أكثر من 15 دقيقة مرتين.'],
            ['student' => $students[9]->id,  'warner' => $users[3]->id,  'title' => 'إنذار - سلوك غير لائق',               'description' => 'إزعاج زملائه أثناء وقت التسميع.'],
            ['student' => $students[10]->id, 'warner' => $users[4]->id,  'title' => 'إنذار ثانٍ - غياب متكرر',             'description' => 'استمرار الغياب دون إبلاغ ولي الأمر.'],
            ['student' => $students[2]->id,  'warner' => $users[7]->id,  'title' => 'تنبيه - عدم مراجعة الواجب',           'description' => 'لم يراجع الطالب الصفحات المطلوبة لأسبوعين.'],
            ['student' => $students[5]->id,  'warner' => $users[9]->id,  'title' => 'تنبيه - تراجع في المستوى',            'description' => 'لوحظ تراجع ملحوظ في مستوى الحفظ.'],
            ['student' => $students[7]->id,  'warner' => $users[11]->id, 'title' => 'تنبيه أول - استخدام الجوال',          'description' => 'تم ضبط الطالب يستخدم هاتفه أثناء الحلقة.'],
            ['student' => $students[8]->id,  'warner' => $users[6]->id,  'title' => 'إنذار - إهمال الواجبات',              'description' => 'عدم التزام الطالب بإنجاز الواجبات المطلوبة.'],
            ['student' => $students[11]->id, 'warner' => $users[5]->id,  'title' => 'تنبيه - عدم احترام المعلم',           'description' => 'أظهر الطالب عدم احترام تجاه المعلم أثناء التقييم.'],
            ['student' => $students[1]->id,  'warner' => $users[6]->id,  'title' => 'تنبيه - التحدث أثناء الدرس',          'description' => 'تحدث الطالب مع زملائه بشكل متكرر أثناء الشرح.'],
            ['student' => $students[3]->id,  'warner' => $users[8]->id,  'title' => 'إنذار - التأخر المتكرر',              'description' => 'تأخر الطالب عن حلقته أكثر من 4 مرات هذا الشهر.'],
        ];

        foreach ($warningsRaw as $index => $d) {
            $d['deduction_points'] = ($index % 5) + 1;
            Warning::create($d);
        }

        // ══════════════════════════════════════════════
        //  18. EXAMS  (14 — unique student+subject+course)
        //      student must be in a circle of the same course
        //      mark ≤ subject.max_marks
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء نتائج الامتحانات...');

        // FK columns: student, subject, course (bare names)
        $examsRaw = [
            ['student' => $students[0]->id,  'subject' => $subjects[0]->id, 'course' => $courses[0]->id, 'mark' => 92],
            ['student' => $students[0]->id,  'subject' => $subjects[1]->id, 'course' => $courses[0]->id, 'mark' => 85],
            ['student' => $students[1]->id,  'subject' => $subjects[0]->id, 'course' => $courses[0]->id, 'mark' => 78],
            ['student' => $students[1]->id,  'subject' => $subjects[1]->id, 'course' => $courses[0]->id, 'mark' => 88],
            ['student' => $students[2]->id,  'subject' => $subjects[0]->id, 'course' => $courses[0]->id, 'mark' => 95],
            ['student' => $students[3]->id,  'subject' => $subjects[2]->id, 'course' => $courses[1]->id, 'mark' => 70],
            ['student' => $students[3]->id,  'subject' => $subjects[3]->id, 'course' => $courses[1]->id, 'mark' => 65],
            ['student' => $students[4]->id,  'subject' => $subjects[4]->id, 'course' => $courses[2]->id, 'mark' => 80],
            ['student' => $students[5]->id,  'subject' => $subjects[4]->id, 'course' => $courses[2]->id, 'mark' => 73],
            ['student' => $students[5]->id,  'subject' => $subjects[5]->id, 'course' => $courses[2]->id, 'mark' => 90],
            ['student' => $students[6]->id,  'subject' => $subjects[4]->id, 'course' => $courses[2]->id, 'mark' => 55],
            ['student' => $students[7]->id,  'subject' => $subjects[6]->id, 'course' => $courses[3]->id, 'mark' => 97],
            ['student' => $students[8]->id,  'subject' => $subjects[8]->id, 'course' => $courses[4]->id, 'mark' => 60],
            ['student' => $students[9]->id,  'subject' => $subjects[10]->id, 'course' => $courses[5]->id, 'mark' => 82],
        ];

        foreach ($examsRaw as $d) {
            Exam::create($d);
        }

        // ══════════════════════════════════════════════
        //  19. ABSENCES  (14 — unique student+course+date, date must be a session_date)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء سجلات الحضور والغياب...');

        // FK columns: student, course (bare names)
        $absencesRaw = [
            ['student' => $students[0]->id, 'course' => $courses[0]->id, 'type' => 'present',       'date' => '2026-01-17', 'note' => null],
            ['student' => $students[0]->id, 'course' => $courses[0]->id, 'type' => 'present',       'date' => '2026-01-24', 'note' => null],
            ['student' => $students[0]->id, 'course' => $courses[0]->id, 'type' => 'full',          'date' => '2026-01-31', 'note' => 'غياب بعذر طبي.'],
            ['student' => $students[1]->id, 'course' => $courses[0]->id, 'type' => 'present',       'date' => '2026-01-17', 'note' => null],
            ['student' => $students[1]->id, 'course' => $courses[0]->id, 'type' => 'first_period',  'date' => '2026-01-24', 'note' => 'تأخر عن الحصة الأولى.'],
            ['student' => $students[2]->id, 'course' => $courses[0]->id, 'type' => 'present',       'date' => '2026-01-17', 'note' => null],
            ['student' => $students[3]->id, 'course' => $courses[1]->id, 'type' => 'present',       'date' => '2026-02-07', 'note' => null],
            ['student' => $students[3]->id, 'course' => $courses[1]->id, 'type' => 'full',          'date' => '2026-02-14', 'note' => 'غياب بدون عذر.'],
            ['student' => $students[4]->id, 'course' => $courses[2]->id, 'type' => 'present',       'date' => '2026-01-24', 'note' => null],
            ['student' => $students[4]->id, 'course' => $courses[2]->id, 'type' => 'second_period', 'date' => '2026-01-31', 'note' => 'انصراف مبكر.'],
            ['student' => $students[5]->id, 'course' => $courses[2]->id, 'type' => 'present',       'date' => '2026-01-24', 'note' => null],
            ['student' => $students[6]->id, 'course' => $courses[2]->id, 'type' => 'full',          'date' => '2026-01-24', 'note' => 'غياب بدون إبلاغ.'],
            ['student' => $students[7]->id, 'course' => $courses[3]->id, 'type' => 'present',       'date' => '2026-03-07', 'note' => null],
            ['student' => $students[8]->id, 'course' => $courses[4]->id, 'type' => 'present',       'date' => '2026-02-21', 'note' => null],
        ];

        foreach ($absencesRaw as $d) {
            StudentCourseAbsence::create($d);
        }

        // ══════════════════════════════════════════════
        //  20. STUDENT MARKS  (12 — unique student+course+circle)
        // ══════════════════════════════════════════════
        $this->command->info('  ✅ إنشاء درجات الطلاب...');

        // FK columns: teacher, student, course, circle (bare names)
        $marksRaw = [
            ['teacher' => $users[6]->id,  'student' => $students[0]->id,  'course' => $courses[0]->id, 'circle' => $circles[0]->id,  'attendance_marks' => 18, 'memorization_marks' => 22, 'reading_improvement_marks' => 15, 'exams_marks' => 20, 'behavior_teacher_marks' => 9,  'behavior_admin_marks' => 8,  'sabr_bonus' => 5],
            ['teacher' => $users[6]->id,  'student' => $students[1]->id,  'course' => $courses[0]->id, 'circle' => $circles[0]->id,  'attendance_marks' => 15, 'memorization_marks' => 20, 'reading_improvement_marks' => 12, 'exams_marks' => 18, 'behavior_teacher_marks' => 8,  'behavior_admin_marks' => 7,  'sabr_bonus' => 3],
            ['teacher' => $users[7]->id,  'student' => $students[2]->id,  'course' => $courses[0]->id, 'circle' => $circles[1]->id,  'attendance_marks' => 20, 'memorization_marks' => 25, 'reading_improvement_marks' => 18, 'exams_marks' => 22, 'behavior_teacher_marks' => 10, 'behavior_admin_marks' => 9,  'sabr_bonus' => 6],
            ['teacher' => $users[8]->id,  'student' => $students[3]->id,  'course' => $courses[1]->id, 'circle' => $circles[2]->id,  'attendance_marks' => 16, 'memorization_marks' => 18, 'reading_improvement_marks' => 14, 'exams_marks' => 15, 'behavior_teacher_marks' => 7,  'behavior_admin_marks' => 6,  'sabr_bonus' => 4],
            ['teacher' => $users[9]->id,  'student' => $students[4]->id,  'course' => $courses[2]->id, 'circle' => $circles[3]->id,  'attendance_marks' => 12, 'memorization_marks' => 15, 'reading_improvement_marks' => 10, 'exams_marks' => 14, 'behavior_teacher_marks' => 6,  'behavior_admin_marks' => 5,  'sabr_bonus' => 2],
            ['teacher' => $users[9]->id,  'student' => $students[5]->id,  'course' => $courses[2]->id, 'circle' => $circles[3]->id,  'attendance_marks' => 17, 'memorization_marks' => 21, 'reading_improvement_marks' => 16, 'exams_marks' => 19, 'behavior_teacher_marks' => 9,  'behavior_admin_marks' => 8,  'sabr_bonus' => 4],
            ['teacher' => $users[10]->id, 'student' => $students[6]->id,  'course' => $courses[2]->id, 'circle' => $circles[4]->id,  'attendance_marks' => 10, 'memorization_marks' => 12, 'reading_improvement_marks' => 8,  'exams_marks' => 10, 'behavior_teacher_marks' => 5,  'behavior_admin_marks' => 4,  'sabr_bonus' => 1],
            ['teacher' => $users[11]->id, 'student' => $students[7]->id,  'course' => $courses[3]->id, 'circle' => $circles[5]->id,  'attendance_marks' => 19, 'memorization_marks' => 24, 'reading_improvement_marks' => 17, 'exams_marks' => 23, 'behavior_teacher_marks' => 10, 'behavior_admin_marks' => 9,  'sabr_bonus' => 7],
            ['teacher' => $users[6]->id,  'student' => $students[8]->id,  'course' => $courses[4]->id, 'circle' => $circles[6]->id,  'attendance_marks' => 14, 'memorization_marks' => 16, 'reading_improvement_marks' => 11, 'exams_marks' => 13, 'behavior_teacher_marks' => 7,  'behavior_admin_marks' => 6,  'sabr_bonus' => 3],
            ['teacher' => $users[7]->id,  'student' => $students[9]->id,  'course' => $courses[5]->id, 'circle' => $circles[7]->id,  'attendance_marks' => 18, 'memorization_marks' => 20, 'reading_improvement_marks' => 15, 'exams_marks' => 17, 'behavior_teacher_marks' => 8,  'behavior_admin_marks' => 7,  'sabr_bonus' => 5],
            ['teacher' => $users[8]->id,  'student' => $students[10]->id, 'course' => $courses[6]->id, 'circle' => $circles[8]->id,  'attendance_marks' => 16, 'memorization_marks' => 19, 'reading_improvement_marks' => 13, 'exams_marks' => 16, 'behavior_teacher_marks' => 8,  'behavior_admin_marks' => 7,  'sabr_bonus' => 4],
            ['teacher' => $users[9]->id,  'student' => $students[11]->id, 'course' => $courses[7]->id, 'circle' => $circles[9]->id,  'attendance_marks' => 13, 'memorization_marks' => 17, 'reading_improvement_marks' => 12, 'exams_marks' => 15, 'behavior_teacher_marks' => 7,  'behavior_admin_marks' => 6,  'sabr_bonus' => 3],
        ];

        foreach ($marksRaw as $d) {
            $d['total_marks'] = $d['attendance_marks'] + $d['memorization_marks']
                              + $d['reading_improvement_marks'] + $d['exams_marks']
                              + $d['behavior_teacher_marks'] + $d['behavior_admin_marks']
                              + $d['sabr_bonus'];
            StudentMark::create($d);
        }

        // ══════════════════════════════════════════════
        //  21. COMPLETE JULY 2026 EVALUATION CYCLE
        // ══════════════════════════════════════════════
        $this->call(JulyEvaluationSeeder::class);

        // ══════════════════════════════════════════════
        //  DONE
        // ══════════════════════════════════════════════
        $this->command->info('');
        $this->command->info('🎉 تم زرع جميع البيانات التجريبية بنجاح!');
        $this->command->info('   ├── '.Permission::count().' صلاحية');
        $this->command->info('   ├── '.Role::count().' أدوار (super-admin, admin, supervisor, teacher, student)');
        $this->command->info('   ├── 12 موظف');
        $this->command->info('   ├── 11 مسجد');
        $this->command->info('   ├── 11 مشروع');
        $this->command->info('   ├── 12 دورة');
        $this->command->info('   ├── 14 مادة');
        $this->command->info('   ├── 14 درس');
        $this->command->info('   ├── '.CourseDate::count().' تاريخ جلسة');
        $this->command->info('   ├── 12 حلقة');
        $this->command->info('   ├── 14 طالب');
        $this->command->info('   ├── '.Note::count().' ملاحظة');
        $this->command->info('   ├── '.Sabr::count().' سبر');
        $this->command->info('   ├── '.Memorization::count().' تسميع');
        $this->command->info('   ├── '.Warning::count().' إنذار');
        $this->command->info('   ├── '.Exam::count().' امتحان');
        $this->command->info('   ├── '.StudentCourseAbsence::count().' سجل حضور/غياب');
        $this->command->info('   ├── '.StudentMark::count().' درجة طالب');
        $this->command->info('   └── دورة تقييم تموز 2026 جاهزة للمعاينة والاحتساب');
        $this->command->info('');
        $this->command->info('🔑 بيانات الدخول: superadmin@gmail.com / password123');
    }
}
