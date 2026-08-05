<?php

namespace App\Services\Evaluation;

use App\Models\EvaluationCandidate;
use App\Models\EvaluationCandidateEnrollment;
use App\Models\EvaluationPeriod;
use App\Models\QuranPeriodAssessment;
use App\Models\TeacherPeriodEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluationInputService
{
    public function __construct(
        private readonly EvaluationPolicyService $policies,
        private readonly EvaluationAuditService $audit,
        private readonly EvaluationSessionService $sessions,
        private readonly EvaluationSourceService $sources,
    ) {}

    public function saveTeacher(EvaluationCandidate $candidate, array $data, User $actor): TeacherPeriodEvaluation
    {
        $this->assertEditable($candidate);
        $enrollment = $this->assertContext(
            $candidate,
            $data['evaluation_period_id'],
            $data['circle_id']
        );
        $settings = $this->policy($candidate)['teacher_evaluation'];
        $period = EvaluationPeriod::findOrFail($data['evaluation_period_id']);

        $this->assertRange($data['behavior_score'], 0, $settings['behavior_maximum'], 'behavior_score');
        $this->assertRange(
            $data['participation_score'],
            0,
            $settings['participation_homework_maximum'],
            'participation_score'
        );
        $this->assertRange(
            $data['teacher_opinion_score'],
            0,
            $settings['teacher_opinion_maximum'],
            'teacher_opinion_score'
        );

        $total = (float) $data['behavior_score']
            + (float) $data['participation_score']
            + (float) $data['teacher_opinion_score'];

        return $this->auditedUpsert(
            TeacherPeriodEvaluation::class,
            [
                'evaluation_candidate_id' => $candidate->id,
                'evaluation_period_id' => $data['evaluation_period_id'],
                'circle_id' => $data['circle_id'],
            ],
            [
                'evaluator_id' => $actor->id,
                'behavior_score' => $data['behavior_score'],
                'participation_score' => $data['participation_score'],
                'teacher_opinion_score' => $data['teacher_opinion_score'],
                'total_score' => $total,
                'evidence' => $this->sources->teacherEvidence(
                    $candidate,
                    $enrollment,
                    $period
                ),
                'comments' => $data['comments'] ?? null,
                'status' => $data['status'],
                'submitted_at' => $data['status'] === 'submitted' ? now() : null,
            ],
            'evaluation.teacher_saved',
            $actor
        );
    }

    public function saveQuran(EvaluationCandidate $candidate, array $data, User $actor): QuranPeriodAssessment
    {
        $this->assertEditable($candidate);
        $enrollment = $this->assertContext(
            $candidate,
            $data['evaluation_period_id'],
            $data['circle_id']
        );
        $period = EvaluationPeriod::findOrFail($data['evaluation_period_id']);
        $target = $this->quranTarget(
            $candidate,
            $data['evaluation_period_id'],
            $enrollment
        );
        $source = $this->sources->quranSummary($candidate, $enrollment, $period);

        if ($target <= 0) {
            throw ValidationException::withMessages([
                'circle_id' => ['معيار القرآن غير منطبق على هذه الحلقة أو لا توجد أيام دوام فعلية في الفترة.'],
            ]);
        }
        if ((float) $source['pages_completed'] < $target && ! $data['below_minimum']) {
            throw ValidationException::withMessages([
                'below_minimum' => ['يجب وضع إشارة «دون الحد الأدنى» عندما يكون الإنجاز أقل من الهدف.'],
            ]);
        }
        if ((float) $source['pages_completed'] >= $target && $data['below_minimum']) {
            throw ValidationException::withMessages([
                'below_minimum' => ['لا يمكن وضع إشارة «دون الحد الأدنى» بعد بلوغ الهدف.'],
            ]);
        }

        return $this->auditedUpsert(
            QuranPeriodAssessment::class,
            [
                'evaluation_candidate_id' => $candidate->id,
                'evaluation_period_id' => $data['evaluation_period_id'],
                'circle_id' => $data['circle_id'],
            ],
            [
                'course_id' => $enrollment->course_id,
                'pages_completed' => $source['pages_completed'],
                'revision_pages' => $source['revision_pages'],
                'target_pages_snapshot' => $target,
                'below_minimum' => $data['below_minimum'],
                'notes' => $data['notes'] ?? null,
                'status' => 'submitted',
                'assessed_by' => $actor->id,
                'assessed_at' => now(),
            ],
            'evaluation.quran_saved',
            $actor
        );
    }

    private function auditedUpsert(
        string $modelClass,
        array $identity,
        array $values,
        string $event,
        User $actor
    ): Model {
        return DB::transaction(function () use ($modelClass, $identity, $values, $event, $actor) {
            $record = $modelClass::query()->where($identity)->lockForUpdate()->first();
            $before = $record?->toArray();
            $record = $modelClass::updateOrCreate($identity, $values);
            $this->audit->record($event, $record, $actor, $before, $record->toArray());

            return $record;
        });
    }

    private function assertEditable(EvaluationCandidate $candidate): void
    {
        if (! in_array($candidate->cycle->status, ['draft', 'data_collection'], true)) {
            throw ValidationException::withMessages([
                'cycle' => ['مدخلات التقييم مقفلة بعد انتهاء مرحلة جمع البيانات.'],
            ]);
        }
    }

    private function assertContext(
        EvaluationCandidate $candidate,
        int $periodId,
        ?int $circleId = null,
        ?int $courseId = null
    ) {
        $periodExists = EvaluationPeriod::query()
            ->whereKey($periodId)
            ->where('evaluation_cycle_id', $candidate->evaluation_cycle_id)
            ->exists();
        if (! $periodExists) {
            throw ValidationException::withMessages([
                'evaluation_period_id' => ['الفترة لا تتبع دورة تقييم الطالب.'],
            ]);
        }

        $enrollment = $candidate->enrollments()
            ->when($circleId, fn ($query) => $query->where('circle_id', $circleId))
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->first();
        if (($circleId || $courseId) && ! $enrollment) {
            throw ValidationException::withMessages([
                'context' => ['الحلقة أو المقرر لا يتبع تسجيل الطالب المجمد في دورة التقييم.'],
            ]);
        }

        return $enrollment;
    }

    private function quranTarget(
        EvaluationCandidate $candidate,
        int $periodId,
        EvaluationCandidateEnrollment $enrollment
    ): float {
        $settings = $this->policy($candidate)['quran']['circle_mode_page_targets'];
        $period = EvaluationPeriod::findOrFail($periodId);
        $dailyTarget = (float) ($settings[$enrollment->quran_mode_snapshot ?? 'none'] ?? 0);

        return $this->sessions->periodCourseDates($enrollment->course_id, $period)->count()
            * $dailyTarget;
    }

    private function policy(EvaluationCandidate $candidate): array
    {
        $candidate->loadMissing('cycle.policy');

        return $this->policies->configuration($candidate->cycle->policy);
    }

    private function assertRange(float|int|string $value, float $minimum, float $maximum, string $field): void
    {
        if ((float) $value < $minimum || (float) $value > $maximum) {
            // يُذكر اسم الحقل داخل الرسالة لأن الواجهة تعرضها في تنبيه واحد
            // لا يوضح بذاته أي درجة تجاوزت الحد المسموح.
            $key = "validation.attributes.{$field}";
            $label = __($key) === $key ? $field : __($key);
            throw ValidationException::withMessages([
                $field => ["{$label}: يجب أن تكون القيمة بين {$minimum} و{$maximum}."],
            ]);
        }
    }
}
