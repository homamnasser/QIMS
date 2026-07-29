<?php

namespace App\Services\Evaluation\Criteria;

use App\Models\EvaluationCandidate;
use App\Models\Subject;
use App\Services\Evaluation\EvaluationSourceService;

class TheoreticalExamCalculator
{
    public function __construct(private readonly EvaluationSourceService $sources) {}

    public function calculate(EvaluationCandidate $candidate, array $policy): array
    {
        $settings = $policy['theoretical_exams'];
        $results = $this->sources->examResults($candidate);

        $configuredSubjectIds = collect($settings['required_subject_ids'] ?? [])
            ->map(fn ($id) => (int) $id);
        $requiredSubjectIds = $configuredSubjectIds->isNotEmpty()
            ? $configuredSubjectIds
            : Subject::query()
                ->whereIn('course_id', $candidate->enrollments->pluck('course_id')->unique())
                ->pluck('id');

        if ($requiredSubjectIds->isEmpty() && $results->isEmpty()) {
            return [
                'key' => 'theoretical_exams',
                'name' => 'الامتحانات النظرية',
                'is_applicable' => false,
                'score' => 0,
                'maximum_score' => 0,
                'inputs' => ['subjects' => [], 'missing_subject_ids' => []],
                'rule_trace' => [
                    'reason' => 'لا توجد مواد نظرية مرتبطة بمقررات الطالب.',
                    'aggregation' => 'weighted_normalized_average',
                ],
                'readiness_status' => 'not_applicable',
                'warnings' => [],
            ];
        }

        $presentSubjectIds = $results->pluck('subject')->unique();
        $missingSubjectIds = $requiredSubjectIds->diff($presentSubjectIds)->values();
        $weightTotal = (float) $results->count();

        $normalized = $weightTotal > 0
            ? $results->sum(function ($result) {
                $maximum = (float) ($result->subjectDetails?->max_marks ?? 0);
                if ($maximum <= 0) {
                    return 0;
                }

                return (float) $result->mark / $maximum;
            }) / $weightTotal * $settings['maximum_score']
            : 0;

        $ready = $results->isNotEmpty()
            && $missingSubjectIds->isEmpty()
            && $results->every(fn ($result) => (float) ($result->subjectDetails?->max_marks ?? 0) > 0);

        $warnings = [];
        if ($results->isEmpty()) {
            $warnings[] = 'لا توجد نتائج امتحانات نظرية معتمدة.';
        }
        if ($missingSubjectIds->isNotEmpty()) {
            $warnings[] = 'بعض المواد النظرية المطلوبة بلا نتيجة.';
        }
        if ($results->contains(fn ($result) => (float) ($result->subjectDetails?->max_marks ?? 0) <= 0)) {
            $warnings[] = 'توجد نتيجة امتحان بدرجة عظمى غير صالحة.';
        }

        return [
            'key' => 'theoretical_exams',
            'name' => 'الامتحانات النظرية',
            'is_applicable' => true,
            'score' => round(max(0, min($settings['maximum_score'], $normalized)), 2),
            'maximum_score' => $settings['maximum_score'],
            'inputs' => [
                'subjects' => $results->map(fn ($result) => [
                    'source_record_id' => $result->id,
                    'subject_id' => $result->subject,
                    'subject_name' => $result->subjectDetails?->name,
                    'score' => (float) $result->mark,
                    'maximum_score' => (float) ($result->subjectDetails?->max_marks ?? 0),
                    'weight' => 1,
                ])->values()->all(),
                'missing_subject_ids' => $missingSubjectIds->all(),
                'normalized_percentage' => round($normalized, 4),
            ],
            'rule_trace' => [
                'aggregation' => 'weighted_normalized_average',
                'required_subject_ids' => $requiredSubjectIds->all(),
                'source' => 'exams',
                'maximum_score_source' => 'subjects.max_marks',
            ],
            'readiness_status' => $ready ? 'ready' : 'missing',
            'warnings' => $warnings,
        ];
    }
}
