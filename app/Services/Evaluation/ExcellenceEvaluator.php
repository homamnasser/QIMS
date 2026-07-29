<?php

namespace App\Services\Evaluation;

class ExcellenceEvaluator
{
    public function evaluate(array $criteria, array $policy): array
    {
        $byKey = collect($criteria)->keyBy('key');
        $settings = $policy['excellence'];

        $attendance = $byKey->get('attendance');
        $exams = $byKey->get('theoretical_exams');
        $reading = $byKey->get('reading');
        $quran = $byKey->get('quran');
        $teacher = $byKey->get('teacher_evaluation');
        $administration = $byKey->get('administration_evaluation');

        $hardChecks = [
            'attendance_minimum' => [
                'passed' => ($attendance['inputs']['attendance_percentage'] ?? 0)
                    >= $settings['hard_requirements']['attendance_percentage'],
                'actual' => $attendance['inputs']['attendance_percentage'] ?? 0,
                'required' => $settings['hard_requirements']['attendance_percentage'],
            ],
            'theoretical_exam_minimum' => [
                'applicable' => $exams['is_applicable'] ?? true,
                'passed' => ! ($exams['is_applicable'] ?? true)
                    || ($exams['score'] ?? 0)
                        >= $settings['hard_requirements']['theoretical_exam_percentage'],
                'actual' => $exams['score'] ?? 0,
                'required' => $settings['hard_requirements']['theoretical_exam_percentage'],
            ],
        ];

        $softChecks = [
            'reading_not_decline' => [
                'applicable' => $reading['is_applicable'] ?? true,
                'passed' => ($reading['inputs']['type'] ?? null) !== 'decline',
                'actual' => $reading['inputs']['type'] ?? null,
                'required' => 'not_decline',
            ],
            'quran_half_target' => [
                'applicable' => $quran['is_applicable'] ?? false,
                'passed' => ! ($quran['is_applicable'] ?? false)
                    || ($quran['inputs']['pages_completed'] ?? 0)
                        >= (($quran['inputs']['target_pages'] ?? 0)
                            * $settings['soft_requirements']['quran_target_fraction']),
                'actual' => $quran['inputs']['pages_completed'] ?? 0,
                'required' => ($quran['inputs']['target_pages'] ?? 0)
                    * $settings['soft_requirements']['quran_target_fraction'],
            ],
            'teacher_minimum' => [
                'applicable' => $teacher['is_applicable'] ?? true,
                'passed' => ($teacher['score'] ?? 0)
                    >= $settings['soft_requirements']['teacher_minimum'],
                'actual' => $teacher['score'] ?? 0,
                'required' => $settings['soft_requirements']['teacher_minimum'],
            ],
            'administration_minimum' => [
                'applicable' => $administration['is_applicable'] ?? true,
                'passed' => ($administration['score'] ?? 0)
                    >= $settings['soft_requirements']['administration_minimum'],
                'actual' => $administration['score'] ?? 0,
                'required' => $settings['soft_requirements']['administration_minimum'],
            ],
        ];

        $hardPassed = collect($hardChecks)->every(fn ($check) => $check['passed']);
        $failedSoftCount = collect($softChecks)
            ->filter(fn ($check) => $check['applicable'] && ! $check['passed'])
            ->count();
        $softPassed = $failedSoftCount
            <= $settings['maximum_failed_soft_requirements'];

        return [
            'is_excellent' => $hardPassed && $softPassed,
            'hard_requirements_passed' => $hardPassed,
            'failed_soft_requirements_count' => $failedSoftCount,
            'maximum_allowed_failed_soft_requirements' => $settings['maximum_failed_soft_requirements'],
            'hard_checks' => $hardChecks,
            'soft_checks' => $softChecks,
        ];
    }
}
