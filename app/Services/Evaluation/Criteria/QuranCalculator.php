<?php

namespace App\Services\Evaluation\Criteria;

use App\Models\EvaluationCandidate;
use App\Models\QuranPeriodAssessment;
use App\Services\Evaluation\EvaluationSessionService;
use App\Services\Evaluation\EvaluationSourceService;

class QuranCalculator
{
    public function __construct(
        private readonly EvaluationSessionService $sessions,
        private readonly EvaluationSourceService $sources,
    ) {}

    public function calculate(EvaluationCandidate $candidate, array $policy): array
    {
        $settings = $policy['quran'];
        $circleModeTargets = $settings['circle_mode_page_targets'];
        $candidate->loadMissing(['cycle.periods', 'enrollments']);

        $manualReviews = QuranPeriodAssessment::query()
            ->where('evaluation_candidate_id', $candidate->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->get()
            ->keyBy(fn ($assessment) => $assessment->evaluation_period_id.'|'.$assessment->circle_id);

        $required = [];
        foreach ($candidate->enrollments as $enrollment) {
            $quranMode = $enrollment->quran_mode_snapshot ?? 'none';
            $dailyTarget = (float) ($circleModeTargets[$quranMode] ?? 0);
            if ($dailyTarget <= 0) {
                continue;
            }

            foreach ($candidate->cycle->periods as $period) {
                $courseDates = $this->sessions->periodCourseDates(
                    $enrollment->course_id,
                    $period
                );
                $target = $courseDates->count() * $dailyTarget;
                if ($target <= 0) {
                    continue;
                }

                $source = $this->sources->quranSummary($candidate, $enrollment, $period);
                $key = $period->id.'|'.$enrollment->circle_id;
                $review = $manualReviews->get($key);
                $required[$key] = [
                    'period_id' => $period->id,
                    'circle_id' => $enrollment->circle_id,
                    'course_id' => $enrollment->course_id,
                    'quran_mode' => $quranMode,
                    'daily_target_pages' => $dailyTarget,
                    'target_pages' => $target,
                    'pages_completed' => $source['pages_completed'],
                    'revision_pages' => $source['revision_pages'],
                    'source_record_ids' => $source['record_ids'],
                    'memorized_page_numbers' => $source['memorized_page_numbers'],
                    'revision_record_ids' => $source['revision_record_ids'],
                    'unresolved_record_ids' => $source['unresolved_record_ids'],
                    'directly_linked_count' => $source['directly_linked_count'],
                    'inferred_count' => $source['inferred_count'],
                    'manual_review_id' => $review?->id,
                    'manual_below_minimum' => (bool) ($review?->below_minimum ?? false),
                    'attendance_days' => $courseDates->map(fn ($courseDate) => [
                        'id' => $courseDate->id,
                        'date' => $courseDate->session_date->toDateString(),
                    ])->values()->all(),
                ];
            }
        }

        if ($required === []) {
            return [
                'key' => 'quran',
                'name' => 'التسميع والحفظ والمراجعة المستمرة',
                'is_applicable' => false,
                'score' => 0,
                'maximum_score' => 0,
                'inputs' => ['target_pages' => 0],
                'rule_trace' => [
                    'reason' => 'لا توجد حلقة تسميع أو تلقين لها أيام دوام فعلية ضمن فترات التقييم.',
                ],
                'readiness_status' => 'not_applicable',
                'warnings' => [],
            ];
        }

        $targetPages = (float) collect($required)->sum('target_pages');
        $pagesCompleted = (float) collect($required)->sum('pages_completed');
        $revisionPages = (float) collect($required)->sum('revision_pages');
        $manualFlagRequired = collect($required)
            ->filter(fn ($requirement) => $requirement['pages_completed'] < $requirement['target_pages']
                && ! $requirement['manual_below_minimum'])
            ->values()
            ->all();
        $unresolvedRecordIds = collect($required)
            ->pluck('unresolved_record_ids')
            ->flatten()
            ->unique()
            ->values()
            ->all();
        $belowMinimum = collect($required)->contains(
            fn ($requirement) => $requirement['pages_completed'] < $requirement['target_pages']
                && $requirement['manual_below_minimum']
        );

        $ready = $manualFlagRequired === [] && $unresolvedRecordIds === [];
        $score = 0.0;
        if ($ready && ! $belowMinimum && $pagesCompleted >= $targetPages) {
            $score = min(
                $settings['maximum_score'],
                $settings['target_reached_score']
                    + (($pagesCompleted - $targetPages) * $settings['extra_page_points'])
            );
        }

        $warnings = [];
        if ($manualFlagRequired !== []) {
            $warnings[] = 'الإنجاز المستخرج من سجلات التسميع دون الحد الأدنى؛ يلزم تأكيد «دون الحد الأدنى» فقط.';
        }
        if ($unresolvedRecordIds !== []) {
            $warnings[] = 'توجد سجلات تسميع قديمة لا يمكن نسبتها بدقة إلى حلقة؛ يجب ربطها بالحَلقة قبل الاعتماد.';
        }
        if ($belowMinimum) {
            $warnings[] = 'تم اعتماد إشارة «دون الحد الأدنى»؛ درجة معيار القرآن تساوي صفراً.';
        }

        return [
            'key' => 'quran',
            'name' => 'التسميع والحفظ والمراجعة المستمرة',
            'is_applicable' => true,
            'score' => round($score, 2),
            'maximum_score' => $settings['maximum_score'],
            'inputs' => [
                'target_pages' => round($targetPages, 2),
                'pages_completed' => round($pagesCompleted, 2),
                'revision_pages' => round($revisionPages, 2),
                'below_minimum' => $belowMinimum,
                'requirements' => array_values($required),
                'manual_flag_required' => $manualFlagRequired,
                'unresolved_source_record_ids' => $unresolvedRecordIds,
            ],
            'rule_trace' => [
                'circle_mode_page_targets' => $circleModeTargets,
                'target_source' => 'circle_quran_mode_snapshot_x_actual_attendance_days',
                'achievement_source' => 'memorizations',
                'manual_input' => 'below_minimum_only_when_derived_pages_are_below_target',
                'target_reached_score' => $settings['target_reached_score'],
                'extra_page_points' => $settings['extra_page_points'],
                'maximum_score' => $settings['maximum_score'],
            ],
            'readiness_status' => $ready ? 'ready' : 'missing',
            'warnings' => $warnings,
        ];
    }
}
