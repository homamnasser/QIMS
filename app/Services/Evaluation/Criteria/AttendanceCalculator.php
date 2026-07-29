<?php

namespace App\Services\Evaluation\Criteria;

use App\Models\EvaluationCandidate;
use App\Models\StudentCourseAbsence;
use App\Services\Evaluation\EvaluationSessionService;

class AttendanceCalculator
{
    public function __construct(private readonly EvaluationSessionService $sessions) {}

    public function calculate(EvaluationCandidate $candidate, array $policy): array
    {
        $settings = $policy['attendance'];
        $courseIds = $candidate->enrollments->pluck('course_id')->unique()->values();
        $sessions = $this->sessions->attendanceSessions($candidate);

        $records = StudentCourseAbsence::query()
            ->where('student', $candidate->student_id)
            ->whereIn('course', $courseIds)
            ->where(function ($query) use ($sessions) {
                $query->whereIn('course_date_id', $sessions->pluck('id'))
                    ->orWhere(function ($legacy) use ($sessions) {
                        $legacy->whereNull('course_date_id')
                            ->whereIn(
                                'date',
                                $sessions->pluck('session_date')
                                    ->map->toDateString()
                                    ->unique()
                            );
                    });
            })
            ->get();

        $recordsByCourseDate = $records
            ->filter(fn ($record) => $record->course_date_id)
            ->keyBy('course_date_id');
        $recordsByLegacyKey = $records
            ->filter(fn ($record) => $record->date)
            ->keyBy(fn ($record) => $record->course.'|'.$record->date->toDateString());

        $counts = [
            'present' => 0,
            'unexcused_absence' => 0,
            'excused_absence' => 0,
            'first_period_late' => 0,
            'second_period_late' => 0,
            'missing_records' => 0,
        ];
        $missingSessions = [];

        foreach ($sessions as $session) {
            $record = $recordsByCourseDate->get($session->id)
                ?? $recordsByLegacyKey->get($session->course_id.'|'.$session->session_date->toDateString());

            if (! $record) {
                $counts['missing_records']++;
                $missingSessions[] = [
                    'course_id' => $session->course_id,
                    'course_date_id' => $session->id,
                    'date' => $session->session_date->toDateString(),
                ];

                continue;
            }

            match ($record->type) {
                'present' => $counts['present']++,
                'full' => $record->is_excused
                    ? $counts['excused_absence']++
                    : $counts['unexcused_absence']++,
                'first_period' => $counts['first_period_late']++,
                'second_period' => $counts['second_period_late']++,
                default => $counts['missing_records']++,
            };
        }

        $equivalentAbsence =
            ($counts['unexcused_absence'] * $settings['unexcused_absence_weight'])
            + ($counts['excused_absence'] * $settings['excused_absence_weight'])
            + floor($counts['first_period_late'] / $settings['first_period_late_divisor'])
            + floor($counts['second_period_late'] / $settings['second_period_late_divisor']);

        $totalSessions = $sessions->count();
        $percentage = $totalSessions > 0
            ? max(0, min(100, (($totalSessions - $equivalentAbsence) / $totalSessions) * 100))
            : 0;

        $score = $this->score($percentage, $settings);
        $maximum = $settings['scoring_mode'] === 'percentage'
            ? $settings['normal_percentage_maximum']
            : $settings['maximum_score'];

        $warnings = [];
        if ($totalSessions === 0) {
            $warnings[] = 'لا توجد جلسات محتسبة للحضور ضمن دورة التقييم.';
        }
        if ($counts['missing_records'] > 0) {
            $warnings[] = 'توجد جلسات بلا سجل حضور آلي؛ لا يجوز اعتماد النتيجة قبل اكتمالها.';
        }

        return [
            'key' => 'attendance',
            'name' => 'الحضور',
            'is_applicable' => true,
            'score' => round($score, 2),
            'maximum_score' => $maximum,
            'inputs' => [
                'total_sessions' => $totalSessions,
                'attendance_percentage' => round($percentage, 4),
                'equivalent_absence' => round($equivalentAbsence, 2),
                'counts' => $counts,
                'missing_sessions' => $missingSessions,
            ],
            'rule_trace' => [
                'denominator_source' => 'actual_course_date_rows_within_evaluation_periods',
                'session_ids' => $sessions->pluck('id')->all(),
                'period_ids' => $candidate->cycle->periods->pluck('id')->all(),
                'scoring_mode' => $settings['scoring_mode'],
                'formula' => 'unexcused + 0.5*excused + floor(first_late/6) + floor(second_late/4)',
                'source' => 'student_course_absences',
            ],
            'readiness_status' => $totalSessions > 0 && $counts['missing_records'] === 0 ? 'ready' : 'missing',
            'warnings' => $warnings,
        ];
    }

    private function score(float $percentage, array $settings): float
    {
        if ($settings['scoring_mode'] === 'percentage') {
            return $percentage;
        }

        if ($percentage < $settings['minimum_percentage']) {
            return 0;
        }

        if ($percentage < $settings['bonus_start_percentage']) {
            return $percentage;
        }

        $score = $percentage
            + ($settings['bonus_multiplier'] * ($percentage - $settings['bonus_start_percentage']));

        return min($settings['maximum_score'], $score);
    }
}
