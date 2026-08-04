<?php

namespace App\Services\Evaluation;

use App\Models\EvaluationCandidate;
use App\Models\EvaluationCandidateEnrollment;
use App\Models\EvaluationPeriod;
use App\Models\Exam;
use App\Models\Memorization;
use App\Models\Note;
use App\Models\ReadingImprovement;
use App\Models\StudentCourseAbsence;
use App\Models\Warning;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class EvaluationSourceService
{
    public function quranSummary(
        EvaluationCandidate $candidate,
        EvaluationCandidateEnrollment $enrollment,
        EvaluationPeriod $period
    ): array {
        $candidate->loadMissing('enrollments');
        $records = Memorization::query()
            ->where('student', $candidate->student_id)
            ->where(function ($query) use ($period): void {
                $query->whereBetween('recorded_at', [
                    $period->start_date->startOfDay(),
                    $period->end_date->endOfDay(),
                ])->orWhere(function ($legacy) use ($period): void {
                    $legacy->whereNull('recorded_at')
                        ->whereBetween('created_at', [
                            $period->start_date->startOfDay(),
                            $period->end_date->endOfDay(),
                        ]);
                });
            })
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        $quranEnrollments = $candidate->enrollments
            ->where('quran_mode_snapshot', '!=', 'none')
            ->values();
        $resolved = $records->filter(
            fn (Memorization $record) => $this->recordBelongsToEnrollment(
                $record,
                $enrollment,
                $quranEnrollments
            )
        )->values();
        $unresolved = $records->reject(
            fn (Memorization $record) => $this->resolvesToAnyEnrollment(
                $record,
                $quranEnrollments
            )
        )->values();

        $memorized = $resolved
            ->where('record_type', '!=', 'revision')
            ->unique('page_number')
            ->values();
        $revised = $resolved
            ->where('record_type', 'revision')
            ->values();

        return [
            'pages_completed' => (float) $memorized->count(),
            'revision_pages' => (float) $revised->count(),
            'record_ids' => $resolved->pluck('id')->all(),
            'memorized_page_numbers' => $memorized->pluck('page_number')->all(),
            'revision_record_ids' => $revised->pluck('id')->all(),
            'unresolved_record_ids' => $unresolved->pluck('id')->all(),
            'directly_linked_count' => $resolved
                ->filter(fn (Memorization $record) => $record->circle_id !== null)
                ->count(),
            'inferred_count' => $resolved
                ->filter(fn (Memorization $record) => $record->circle_id === null)
                ->count(),
        ];
    }

    public function examResults(EvaluationCandidate $candidate): Collection
    {
        [$start, $end] = $this->sourceWindow($candidate);

        // النافذة على created_at لا updated_at: تصحيح علامة بعد data_cutoff_at كان
        // يدفع updated_at خارج النافذة فتختفي العلامة من الحساب صامتاً وتنخفض درجة
        // الطالب. وبالمقابل كانت علامة أقدم من الدورة تدخلها بمجرد تعديلها.
        // بقية مصادر التقييم (الإنذارات، القراءة، الملاحظات) تستخدم created_at أصلاً.
        return Exam::query()
            ->with('subjectDetails:id,name,min_marks,max_marks,course_id,shared_with_subject_id')
            ->where('student', $candidate->student_id)
            ->whereIn('course', $candidate->enrollments->pluck('course_id')->unique())
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('subject')
            ->get();
    }

    public function readingRecords(EvaluationCandidate $candidate): Collection
    {
        [$start, $end] = $this->sourceWindow($candidate);

        return ReadingImprovement::query()
            ->where('student', $candidate->student_id)
            ->whereIn('course', $candidate->enrollments->pluck('course_id')->unique())
            ->where(function ($query) use ($candidate, $start, $end): void {
                $query->where('evaluation_candidate_id', $candidate->id)
                    ->orWhere(function ($operational) use ($start, $end): void {
                        $operational->whereNull('evaluation_candidate_id')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->latest('created_at')
            ->latest('id')
            ->get();
    }

    public function warnings(EvaluationCandidate $candidate): Collection
    {
        [$start, $end] = $this->sourceWindow($candidate);

        return Warning::query()
            ->where('student', $candidate->student_id)
            ->whereBetween('created_at', [$start, $end])
            ->oldest('created_at')
            ->oldest('id')
            ->get();
    }

    public function teacherEvidence(
        EvaluationCandidate $candidate,
        EvaluationCandidateEnrollment $enrollment,
        EvaluationPeriod $period
    ): array {
        $notes = Note::query()
            ->where('student_id', $candidate->student_id)
            ->whereBetween('created_at', [
                $period->start_date->startOfDay(),
                $period->end_date->endOfDay(),
            ])
            ->get(['id', 'user_id', 'title', 'created_at']);
        $warnings = Warning::query()
            ->where('student', $candidate->student_id)
            ->whereBetween('created_at', [
                $period->start_date->startOfDay(),
                $period->end_date->endOfDay(),
            ])
            ->get(['id', 'warner', 'title', 'deduction_points', 'created_at']);
        $attendance = StudentCourseAbsence::query()
            ->where('student', $candidate->student_id)
            ->where('course', $enrollment->course_id)
            ->whereBetween('date', [
                $period->start_date->toDateString(),
                $period->end_date->toDateString(),
            ])
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'source' => 'operational_records',
            'note_ids' => $notes->pluck('id')->all(),
            'warning_ids' => $warnings->pluck('id')->all(),
            'notes_count' => $notes->count(),
            'warnings_count' => $warnings->count(),
            'attendance_counts' => $attendance->map(fn ($count) => (int) $count)->all(),
        ];
    }

    private function recordBelongsToEnrollment(
        Memorization $record,
        EvaluationCandidateEnrollment $enrollment,
        Collection $quranEnrollments
    ): bool {
        if ($record->circle_id !== null) {
            return (int) $record->circle_id === (int) $enrollment->circle_id;
        }
        if ($record->course_id !== null) {
            return (int) $record->course_id === (int) $enrollment->course_id
                && $quranEnrollments
                    ->where('course_id', $record->course_id)
                    ->count() === 1;
        }

        $teacherMatches = $quranEnrollments
            ->where('teacher_id', $record->giver)
            ->values();
        if ($teacherMatches->count() === 1) {
            return (int) $teacherMatches->first()->circle_id === (int) $enrollment->circle_id;
        }

        return $quranEnrollments->count() === 1
            && (int) $quranEnrollments->first()->circle_id === (int) $enrollment->circle_id;
    }

    private function resolvesToAnyEnrollment(
        Memorization $record,
        Collection $quranEnrollments
    ): bool {
        return $quranEnrollments->contains(
            fn (EvaluationCandidateEnrollment $enrollment) => $this->recordBelongsToEnrollment(
                $record,
                $enrollment,
                $quranEnrollments
            )
        );
    }

    private function sourceWindow(EvaluationCandidate $candidate): array
    {
        $candidate->loadMissing('cycle');
        $start = CarbonImmutable::parse($candidate->cycle->start_date)->startOfDay();
        $cycleEnd = CarbonImmutable::parse($candidate->cycle->end_date)->endOfDay();
        $cutoff = $candidate->cycle->data_cutoff_at
            ? CarbonImmutable::parse($candidate->cycle->data_cutoff_at)
            : CarbonImmutable::now();

        return [$start, $cutoff->lessThan($cycleEnd) ? $cutoff : $cycleEnd];
    }
}
