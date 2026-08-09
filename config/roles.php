<?php

use App\Enums\RoleFamily;

$superviseCapability = 'الإشراف على المشاريع والكورسات';
$fieldOperationsCapability = 'الإشراف الميداني الكامل';

// كل دور بشري يحتاجهما ليدخل ويخرج؛ لا معنى لحدّ أدنى لا يتضمنهما.
$authenticationPermissions = [
    'تسجيل الدخول',
    'تسجيل الخروج',
];

$attendanceAndAbsencePermissions = [
    'عرض كافة الغيابات',
    'إنشاء غياب',
    'عرض تفاصيل الغياب',
    'تعديل الغياب',
    'حذف غياب',
];

$teacherAttendanceAndAbsencePermissions = array_values(array_diff(
    $attendanceAndAbsencePermissions,
    ['تعديل الغياب', 'حذف غياب']
));

$readingImprovementPermissions = [
    'عرض كافة تقييمات القراءة',
    'إنشاء تقييم قراءة',
    'عرض تفاصيل تقييم القراءة',
    'تعديل تقييم القراءة',
    'حذف تقييم القراءة',
];

$teacherPermissions = [
    ...$authenticationPermissions,
    'عرض كافة الكورسات',
    'عرض تفاصيل الكورس',
    'عرض كافة المواد',
    'عرض تفاصيل المادة',
    'عرض كافة الدروس',
    'عرض تفاصيل الدرس',
    'عرض تواريخ الكورس',
    'عرض دروس التاريخ',
    'عرض المنهج الدراسي للكورس',
    'عرض منهج حلقتي',
    'عرض تفاصيل الحلقة',
    'عرض كافة الحلقات',
    'عرض كافة الطلاب',
    'عرض تفاصيل الطالب',
    'عرض طلاب الحلقة',
    'إنشاء ملاحظة',
    'عرض ملاحظات الطالب',
    'عرض ملاحظاتي',
    'إنشاء سبر',
    'عرض سبر الطالب',
    'عرض سبري',
    'تعديل نتيجة السبر',
    'إنشاء تسميع',
    'عرض تسميع الطالب',
    'عرض تسميعاتي',
    'إنشاء إنذار',
    'عرض تفاصيل الإنذار',
    'عرض إنذاراتي',
    'إنشاء امتحان',
    'عرض تفاصيل الامتحان',
    'امتحاناتي',
    ...$teacherAttendanceAndAbsencePermissions,
    // بطاقة التقييم النهائي على الويب لا تُفتح بصلاحية الإدخال وحدها: القائمة
    // والبطاقة تمران بـ«عرض دورات التقييم»، ولوحة الجاهزية بـ«عرض تقييمات
    // الطلاب النهائية». كلاهما مقيّد أصلاً بدورات المعلم عبر EvaluationAccessService.
    'عرض دورات التقييم',
    'عرض تقييمات الطلاب النهائية',
    'إدخال تقييم المدرس',
    'إدخال تقييم القرآن',
    ...$readingImprovementPermissions,
];

$studentCapabilities = [
    'mosque' => 'عرض مسجدي الدراسي',
    'circles' => 'عرض حلقاتي الدراسية',
    'courses' => 'عرض كورساتي التعليمية',
    'course_schedules' => 'عرض جداول كورساتي',
    'notes' => 'عرض ملاحظاتي كطالب',
    'sabrs' => 'عرض سبري كطالب',
    'memorizations' => 'عرض تسميعاتي كطالب',
    'warnings' => 'عرض إنذاراتي كطالب',
    'exams' => 'عرض امتحاناتي كطالب',
    'reading_improvements' => 'عرض تقييمات قراءتي كطالب',
    'final_results' => 'عرض نتيجتي النهائية',
    'certificates' => 'تحميل شهادتي النهائية',
];

// الدخول والخروج خارج خريطة القدرات الذاتية عمداً: تلك الخريطة تُستخدم لحصر ما
// لا يملكه غير الطالب، والدخول يملكه الجميع.
$studentPermissions = [
    ...$authenticationPermissions,
    ...array_values($studentCapabilities),
];

/*
 * المشرف الميداني: رصد حضور جلسات حلقاته فقط.
 * ما دون ذلك — الملاحظات والإنذارات والسبر ودورات التقييم وقدرة «الإشراف
 * الميداني الكامل» التي تتخطى حصر السجلات بصاحبها — خارج مسؤوليته، فلا يأخذه.
 * الباقي هنا هو ارتباطات الرصد فقط: الحلقة، وطلابها، وجلسات كورسها.
 */
$fieldSupervisorPermissions = [
    ...$authenticationPermissions,
    'عرض كافة الحلقات',
    'عرض تفاصيل الحلقة',
    'عرض طلاب الحلقة',
    'عرض كافة الطلاب',
    'عرض تفاصيل الطالب',
    'عرض تواريخ الكورس',
    ...$attendanceAndAbsencePermissions,
];

$administrationPermissions = [
    ...$authenticationPermissions,
    $superviseCapability,
];

return [
    'protected_names' => [
        'super-admin',
        'admin',
        'supervisor',
        'field-supervisor',
        'teacher',
        'student',
    ],

    'capabilities' => [
        'supervise' => $superviseCapability,
        'field_operations' => $fieldOperationsCapability,
    ],

    'mobile_staff_families' => [
        RoleFamily::Teacher->value,
        RoleFamily::FieldSupervisor->value,
    ],

    'authentication_permissions' => $authenticationPermissions,
    'student_capabilities' => $studentCapabilities,
    'student_permissions' => array_values(array_unique($studentPermissions)),
    'field_supervisor_permissions' => array_values(array_unique($fieldSupervisorPermissions)),
    'attendance_and_absence_permissions' => $attendanceAndAbsencePermissions,
    'teacher_attendance_and_absence_permissions' => $teacherAttendanceAndAbsencePermissions,
    'teacher_permissions' => array_values(array_unique($teacherPermissions)),
    'reading_improvement_permissions' => $readingImprovementPermissions,

    // Kept for staff-facing legacy endpoints. Student-family roles are migrated
    // away from these ambiguous names to the explicit capabilities above.
    'legacy_student_capabilities' => [
        'notes' => 'عرض ملاحظاتي',
        'sabrs' => 'عرض سبري',
        'memorizations' => 'عرض تسميعاتي',
        'warnings' => 'عرض إنذاراتي',
        'exams' => 'امتحاناتي',
    ],

    // These permissions are always added when a role is saved with the
    // corresponding family. All other permissions remain fully configurable.
    'family_permissions' => [
        RoleFamily::Admin->value => $administrationPermissions,
        RoleFamily::Supervisor->value => $administrationPermissions,
        RoleFamily::FieldSupervisor->value => array_values(array_unique($fieldSupervisorPermissions)),
        RoleFamily::Teacher->value => array_values(array_unique($teacherPermissions)),
        RoleFamily::Student->value => array_values(array_unique($studentPermissions)),
    ],

    /*
     * العائلات التي يمثّل حدّها الأدنى مجموعتها الكاملة، فيُزامَن دورها النظامي
     * عليها تماماً (إضافةً وحذفاً) عبر `roles:sync-minimums`.
     * الإدارة والإشراف خارجها عمداً: حدّهما أرضية تُبنى فوقها لا سقف.
     */
    'exact_families' => [
        RoleFamily::FieldSupervisor->value,
        RoleFamily::Teacher->value,
        RoleFamily::Student->value,
    ],
];
