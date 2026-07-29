<?php

return [
    'default_policy_name' => 'سياسة التقييم النهائية',

    'permissions' => [
        'عرض دورات التقييم',
        'إدارة دورات التقييم',
        'عرض تقييمات الطلاب النهائية',
        'إدخال تقييم القرآن',
        'إدخال تقييم المدرس',
        'إدارة تقييم الإدارة',
        'احتساب النتائج النهائية',
        'اعتماد النتائج النهائية',
        'نشر النتائج النهائية',
        'إدارة التكريم النهائي',
        'إصدار الشهادات النهائية',
        'عرض سجل تدقيق التقييم',
    ],

    'default_policy' => [
        'schema_version' => 1,
        'attendance' => [
            'scoring_mode' => 'nasheea_bonus',
            'minimum_percentage' => 60,
            'bonus_start_percentage' => 90,
            'bonus_multiplier' => 3,
            'maximum_score' => 130,
            'normal_percentage_maximum' => 100,
            'excused_absence_weight' => 0.5,
            'unexcused_absence_weight' => 1,
            'first_period_late_divisor' => 6,
            'second_period_late_divisor' => 4,
        ],
        'reading' => [
            'maximum_score' => 25,
            'points' => [
                'significant_improvement' => 25,
                'slight_improvement' => 10,
                'no_improvement' => -5,
                'decline' => -15,
            ],
            'levels' => [
                'level_1' => [
                    'slight_difference' => 2,
                    'significant_difference' => 4,
                ],
                'level_2' => [
                    'slight_difference' => 1.5,
                    'significant_difference' => 3,
                ],
                'level_3' => [
                    'decline_below_final_score' => 7,
                    'promotion_above_final_score' => 8,
                ],
            ],
        ],
        'quran' => [
            'maximum_score' => 100,
            'target_reached_score' => 70,
            'extra_page_points' => 1,
            'below_minimum_score' => 0,
            'circle_mode_page_targets' => [
                'recitation' => 1,
                'talqin' => 0.5,
            ],
        ],
        'theoretical_exams' => [
            'maximum_score' => 100,
            'minimum_excellence_percentage' => 50,
            'required_subject_ids' => [],
        ],
        'teacher_evaluation' => [
            'maximum_score' => 50,
            'behavior_maximum' => 20,
            'participation_homework_maximum' => 20,
            'teacher_opinion_maximum' => 10,
            'aggregation' => 'average',
            'minimum_excellence_score' => 20,
        ],
        'administration_evaluation' => [
            'maximum_score' => 50,
            'minimum_deduction' => 1,
            'maximum_deduction' => 5,
            'minimum_excellence_score' => 20,
        ],
        'sabr_bonus' => [
            'internal' => 25,
            'awqaf' => 40,
            'once_per_student_part' => true,
        ],
        'excellence' => [
            'hard_requirements' => [
                'attendance_percentage' => 60,
                'theoretical_exam_percentage' => 50,
            ],
            'soft_requirements' => [
                'reading_must_not_decline' => true,
                'quran_target_fraction' => 0.5,
                'teacher_minimum' => 20,
                'administration_minimum' => 20,
            ],
            'maximum_failed_soft_requirements' => 1,
        ],
        'recognition' => [
            'season' => 'winter',
            'after_period_sequence' => 2,
            'top_students_count' => 15,
            'perfect_attendance_percentage' => 100,
            'top_tiers' => [
                '1' => 'first',
                '2' => 'second',
                '3' => 'third',
                '4-15' => 'top_group',
            ],
            'avoid_duplicate_material_gifts' => true,
        ],
        'base_maximum_when_all_criteria_apply' => 455,
    ],

    'certificate' => [
        'disk' => env('EVALUATION_CERTIFICATE_DISK', 'local'),
        'chrome_binary' => env('EVALUATION_CHROME_BINARY', '/usr/bin/google-chrome'),
        'public_verification_url' => env(
            'EVALUATION_VERIFICATION_URL',
            rtrim((string) env('APP_URL', 'http://localhost'), '/').'/api/public/certificates/verify'
        ),
        'issuer_name' => env('EVALUATION_CERTIFICATE_ISSUER', 'إدارة البرنامج التعليمي'),
    ],
];
