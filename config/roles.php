<?php

use App\Enums\RoleFamily;

$fieldOperationsCapability = 'الإشراف الميداني الكامل';

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
    'final_results' => 'عرض نتيجتي النهائية',
    'certificates' => 'تحميل شهادتي النهائية',
];

$fieldSupervisorPermissions = [
    'تسجيل الدخول',
    'تسجيل الخروج',
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
    'عرض كافة الغيابات',
    'إنشاء غياب',
    'عرض تفاصيل الغياب',
    'تعديل الغياب',
    'حذف غياب',
    'إنشاء إنذار',
    'عرض تفاصيل الإنذار',
    'حذف إنذار',
    'عرض كافة الإنذارات',
    'عرض إنذاراتي',
    'إنشاء ملاحظة',
    'عرض ملاحظات الطالب',
    'عرض ملاحظاتي',
    'حذف ملاحظة',
    'إنشاء سبر',
    'عرض سبر الطالب',
    'عرض سبري',
    'تعديل نتيجة السبر',
    'عرض كافة السبور',
    'حذف سبر',
    ...config('evaluation.permissions'),
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
        'supervise' => 'الإشراف على المشاريع والكورسات',
        'field_operations' => $fieldOperationsCapability,
    ],

    'mobile_staff_families' => [
        RoleFamily::Teacher->value,
        RoleFamily::FieldSupervisor->value,
    ],

    'student_capabilities' => $studentCapabilities,
    'field_supervisor_permissions' => array_values(array_unique([
        $fieldOperationsCapability,
        ...$fieldSupervisorPermissions,
    ])),

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
        RoleFamily::Admin->value => ['الإشراف على المشاريع والكورسات'],
        RoleFamily::Supervisor->value => ['الإشراف على المشاريع والكورسات'],
        RoleFamily::FieldSupervisor->value => array_values(array_unique([
            $fieldOperationsCapability,
            ...$fieldSupervisorPermissions,
        ])),
        RoleFamily::Student->value => array_values($studentCapabilities),
    ],
];
