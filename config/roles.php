<?php

use App\Enums\RoleFamily;

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
];

return [
    'protected_names' => [
        'super-admin',
        'admin',
        'supervisor',
        'teacher',
        'student',
    ],

    'capabilities' => [
        'supervise' => 'الإشراف على المشاريع والكورسات',
    ],

    'student_capabilities' => $studentCapabilities,

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
        RoleFamily::Student->value => array_values($studentCapabilities),
    ],
];
