<?php

use App\Enums\RoleFamily;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Project;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Report catalog
|--------------------------------------------------------------------------
|
| مصدر الحقيقة الوحيد لنظام التقارير. إضافة كيان أو حقل جديد لا تحتاج كودًا:
| يكفي سطر هنا. الواجهة تبني نفسها من هذا الملف عبر `GET /api/reports`.
|
| مفاتيح الحقل:
|   label       عنوان العمود (إلزامي)
|   default     يُحدَّد تلقائيًا عند اختيار الكيان
|   path        مسار القيمة داخل النموذج، نقطي للعلاقات: 'mosque.name'
|               (الافتراضي: مفتاح الحقل نفسه)
|   paths       عدة مسارات تُدمج بـ separator (الاسم الأول + الأخير)
|   relation    علاقة متعدّدة تُصيَّر قائمة عبر template
|   template    قالب العنصر: '{session_date|day_date}: {lessons.*.name}'
|   order       عمود ترتيب عناصر relation
|   separator   فاصل الدمج (الافتراضي ' ' للمسارات و'، ' للقوائم)
|   count       اسم علاقة تُحسب عبر withCount بدل جلب عناصرها
|   map         خريطة قيمة ← تسمية معروضة
|   format      'date' أو 'day_date'
|   permission  صلاحية إضافية يُخفى الحقل بدونها
|
| مفاتيح الفلتر: key, label, type ('select'|'text'), path, options
| (مصفوفة ثابتة أو 'distinct' لاشتقاقها من قاعدة البيانات مع تخزين مؤقت),
| cast ('boolean').
|
| صلاحية الكيان هي بوابة الوصول، ونطاق المسجد يُطبَّق تلقائيًا عبر
| StaffMosqueScope فلا يرى موظف مسجدٍ بيانات مسجد آخر في أي تقرير.
|
*/

/** @param array<string, string> $map */
$options = static fn (array $map): array => array_map(
    static fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
    array_keys($map),
    array_values($map)
);

$activeStates = ['1' => 'نشط', '0' => 'غير نشط'];
$readingLevels = [
    'level_1' => 'المستوى الأول',
    'level_2' => 'المستوى الثاني',
    'level_3' => 'المستوى الثالث',
];
$socialStates = [
    'married' => 'متزوج',
    'divorced' => 'مطلق',
    'widowed' => 'أرمل',
];
$roleFamilies = [
    RoleFamily::SuperAdmin->value => 'سوبر أدمن',
    RoleFamily::Admin->value => 'إدارة',
    RoleFamily::Supervisor->value => 'إشراف',
    RoleFamily::FieldSupervisor->value => 'إشراف ميداني',
    RoleFamily::Teacher->value => 'تعليم',
    RoleFamily::Student->value => 'طلاب',
    RoleFamily::Custom->value => 'مخصص',
];

return [

    'default_per_page' => 25,

    'max_per_page' => 200,

    // حجم الدفعة أثناء التصدير: الصفوف تُكتب على التدفّق دفعةً بدفعة فتبقى
    // الذاكرة ثابتة مهما بلغ عدد السجلات.
    'export_chunk' => 1000,

    'options_cache_ttl' => 300,

    'entities' => [

        'staff' => [
            'label' => 'الموظفون',
            'icon' => 'users',
            'permission' => 'عرض كافة الموظفين',
            'model' => User::class,
            'sort' => ['id', 'desc'],
            'search' => ['first_name', 'last_name', 'email', 'phone'],
            'fields' => [
                'first_name' => ['label' => 'الاسم الأول', 'default' => true],
                'last_name' => ['label' => 'اسم العائلة', 'default' => true],
                'email' => ['label' => 'البريد الإلكتروني', 'default' => true],
                'phone' => ['label' => 'رقم الهاتف', 'default' => true],
                'birth_date' => ['label' => 'تاريخ الميلاد', 'format' => 'date'],
                'role' => [
                    'label' => 'الدور/الرتبة',
                    'default' => true,
                    'relation' => 'roles',
                    'template' => '{name}',
                ],
                'role_family' => [
                    'label' => 'عائلة الدور',
                    'relation' => 'roles',
                    'template' => '{role_family}',
                    'map' => $roleFamilies,
                ],
                'mosque' => ['label' => 'المسجد', 'path' => 'mosque.name'],
            ],
            'filters' => [
                [
                    'key' => 'role_family',
                    'label' => 'تصفية حسب الدور',
                    'type' => 'select',
                    'path' => 'roles.role_family',
                    'options' => $options($roleFamilies),
                ],
            ],
        ],

        'projects' => [
            'label' => 'المشاريع',
            'icon' => 'folder',
            'permission' => 'عرض كافة المشاريع',
            'model' => Project::class,
            'sort' => ['id', 'desc'],
            'search' => ['name', 'audience', 'description'],
            'fields' => [
                'name' => ['label' => 'اسم المشروع', 'default' => true],
                'description' => ['label' => 'الوصف', 'default' => true],
                'audience' => ['label' => 'الفئة المستهدفة', 'default' => true],
                'supervisor' => [
                    'label' => 'المشرف المسؤول',
                    'default' => true,
                    'paths' => ['supervisorUser.first_name', 'supervisorUser.last_name'],
                ],
                'courses_count' => ['label' => 'عدد الكورسات', 'count' => 'courses'],
                'is_active' => ['label' => 'الحالة', 'default' => true, 'map' => $activeStates],
            ],
            'filters' => [
                [
                    'key' => 'is_active',
                    'label' => 'تصفية حسب الحالة',
                    'type' => 'select',
                    'path' => 'is_active',
                    'cast' => 'boolean',
                    'options' => $options($activeStates),
                ],
            ],
        ],

        'courses' => [
            'label' => 'الكورسات التعليمية',
            'icon' => 'academy',
            'permission' => 'عرض كافة الكورسات',
            'model' => Course::class,
            'sort' => ['id', 'desc'],
            'search' => ['name', 'description', 'mosque.name', 'project.name'],
            'fields' => [
                'name' => ['label' => 'اسم الكورس', 'default' => true],
                'description' => ['label' => 'الوصف'],
                'mosque' => ['label' => 'المسجد التابع له', 'default' => true, 'path' => 'mosque.name'],
                'project' => ['label' => 'المشروع التابع له', 'default' => true, 'path' => 'project.name'],
                'supervisor' => [
                    'label' => 'المشرف',
                    'default' => true,
                    'paths' => ['supervisor.first_name', 'supervisor.last_name'],
                ],
                'parent_course' => ['label' => 'الكورس الأب', 'path' => 'parentCourse.name'],
                'start_date' => ['label' => 'تاريخ البدء', 'default' => true, 'format' => 'date'],
                'end_date' => ['label' => 'تاريخ الانتهاء', 'default' => true, 'format' => 'date'],
                'is_active' => ['label' => 'الحالة', 'default' => true, 'map' => $activeStates],
            ],
            'filters' => [
                [
                    'key' => 'is_active',
                    'label' => 'تصفية حسب الحالة',
                    'type' => 'select',
                    'path' => 'is_active',
                    'cast' => 'boolean',
                    'options' => $options($activeStates),
                ],
                [
                    'key' => 'mosque',
                    'label' => 'تصفية حسب المسجد',
                    'type' => 'text',
                    'path' => 'mosque.name',
                ],
            ],
        ],

        'subjects' => [
            'label' => 'المواد الدراسية',
            'icon' => 'book',
            'permission' => 'عرض كافة المواد',
            'model' => Subject::class,
            'sort' => ['id', 'desc'],
            'search' => ['name', 'description', 'course.name'],
            'fields' => [
                'name' => ['label' => 'اسم المادة', 'default' => true],
                'description' => ['label' => 'الوصف'],
                'min_marks' => ['label' => 'الدرجة الصغرى للنجاح', 'default' => true],
                'max_marks' => ['label' => 'الدرجة العظمى النهائية', 'default' => true],
                'course' => ['label' => 'الكورس التابع له', 'default' => true, 'path' => 'course.name'],
                'shared_subject' => ['label' => 'المادة المشتركة المرتبطة', 'path' => 'sharedSubject.name'],
            ],
            'filters' => [
                [
                    'key' => 'course',
                    'label' => 'تصفية حسب الكورس',
                    'type' => 'text',
                    'path' => 'course.name',
                ],
            ],
        ],

        'lessons' => [
            'label' => 'الدروس المنهجية',
            'icon' => 'document',
            'permission' => 'عرض كافة الدروس',
            'model' => Lesson::class,
            'sort' => ['id', 'desc'],
            'search' => ['name', 'description', 'subject.name'],
            'fields' => [
                'name' => ['label' => 'اسم الدرس', 'default' => true],
                'description' => ['label' => 'الوصف'],
                'start_page' => ['label' => 'صفحة البدء', 'default' => true],
                'end_page' => ['label' => 'صفحة النهاية', 'default' => true],
                'subject' => ['label' => 'المادة الدراسية التابع لها', 'default' => true, 'path' => 'subject.name'],
                'course' => ['label' => 'الكورس', 'path' => 'subject.course.name'],
            ],
            'filters' => [
                [
                    'key' => 'subject',
                    'label' => 'تصفية حسب المادة',
                    'type' => 'text',
                    'path' => 'subject.name',
                ],
            ],
        ],

        'circles' => [
            'label' => 'الحلقات الدراسية',
            'icon' => 'circle',
            'permission' => 'عرض كافة الحلقات',
            'model' => Circle::class,
            'sort' => ['id', 'desc'],
            'search' => ['name', 'course.name', 'teacher.first_name', 'teacher.last_name'],
            'fields' => [
                'name' => ['label' => 'اسم الحلقة', 'default' => true],
                'course' => ['label' => 'الكورس التابع له', 'default' => true, 'path' => 'course.name'],
                'teacher' => [
                    'label' => 'المعلم المشرف',
                    'default' => true,
                    'paths' => ['teacher.first_name', 'teacher.last_name'],
                ],
                'students_count' => ['label' => 'عدد الطلاب', 'default' => true, 'count' => 'students'],
            ],
            'filters' => [
                [
                    'key' => 'course',
                    'label' => 'تصفية حسب الكورس',
                    'type' => 'text',
                    'path' => 'course.name',
                ],
            ],
        ],

        'students' => [
            'label' => 'الطلاب',
            'icon' => 'student',
            'permission' => 'عرض كافة الطلاب',
            'model' => Student::class,
            'sort' => ['id', 'desc'],
            'search' => ['first_name', 'last_name', 'username', 'selfnumber', 'phone_number'],
            'fields' => [
                'first_name' => ['label' => 'الاسم الأول', 'default' => true],
                'last_name' => ['label' => 'اسم العائلة', 'default' => true],
                'selfnumber' => ['label' => 'الرقم الذاتي'],
                'username' => ['label' => 'اسم المستخدم'],
                'phone_number' => ['label' => 'رقم الهاتف', 'default' => true],
                'birth_date' => ['label' => 'تاريخ الميلاد', 'format' => 'date'],
                'academic_class' => ['label' => 'الصف الدراسي', 'default' => true],
                'reading_level' => ['label' => 'مستوى القراءة', 'default' => true, 'map' => $readingLevels],
                'father_name' => ['label' => 'اسم الأب', 'default' => true],
                'parent_social_state' => ['label' => 'الحالة الاجتماعية للأبوين', 'map' => $socialStates],
                'father_phone' => ['label' => 'رقم هاتف الأب'],
                'mosque' => ['label' => 'المسجد', 'path' => 'mosque.name'],
                'circles' => [
                    'label' => 'الحلقات المسجَّل بها',
                    'relation' => 'studentCircles',
                    'template' => '{circleDetails.name}',
                ],
            ],
            'filters' => [
                [
                    'key' => 'reading_level',
                    'label' => 'تصفية حسب مستوى القراءة',
                    'type' => 'select',
                    'path' => 'reading_level',
                    'options' => $options($readingLevels),
                ],
                [
                    'key' => 'academic_class',
                    'label' => 'تصفية حسب الصف الدراسي',
                    'type' => 'select',
                    'path' => 'academic_class',
                    'options' => 'distinct',
                ],
                [
                    'key' => 'parent_social_state',
                    'label' => 'تصفية حسب الحالة الاجتماعية',
                    'type' => 'select',
                    'path' => 'parent_social_state',
                    'options' => $options($socialStates),
                ],
            ],
        ],

        'course_dates_lessons' => [
            'label' => 'تاريخ الكورسات والدروس',
            'icon' => 'calendar',
            'permission' => 'عرض كافة الكورسات',
            'model' => Course::class,
            'sort' => ['id', 'desc'],
            'search' => ['name'],
            'fields' => [
                'course_name' => ['label' => 'اسم الدورة (الكورس)', 'default' => true, 'path' => 'name'],
                'start_date' => ['label' => 'تاريخ بداية الدورة', 'default' => true, 'format' => 'date'],
                'end_date' => ['label' => 'تاريخ نهاية الدورة', 'default' => true, 'format' => 'date'],
                'course_days' => [
                    'label' => 'أيام الدورة',
                    'default' => true,
                    'relation' => 'courseDates',
                    'order' => 'session_date',
                    'template' => '{session_date|day_date}',
                    'separator' => ' | ',
                ],
                'assigned_lessons' => [
                    'label' => 'الدروس المسندة إلى أيام الدورة',
                    'default' => true,
                    'relation' => 'courseDates',
                    'order' => 'session_date',
                    'template' => '{session_date|day_date}: {lessons.*.name}',
                    'separator' => "\n",
                ],
                'is_active' => ['label' => 'الحالة', 'map' => $activeStates],
            ],
            'filters' => [
                [
                    'key' => 'is_active',
                    'label' => 'تصفية حسب الحالة',
                    'type' => 'select',
                    'path' => 'is_active',
                    'cast' => 'boolean',
                    'options' => $options($activeStates),
                ],
            ],
        ],

    ],
];
