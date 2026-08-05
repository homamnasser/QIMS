<?php

namespace App\Services\Evaluation;

use App\Models\Course;
use App\Models\EvaluationCandidate;
use App\Models\EvaluationCandidateEnrollment;
use App\Models\EvaluationCycle;
use App\Models\EvaluationExamResult;
use App\Models\EvaluationPeriod;
use App\Models\QuranPeriodAssessment;
use App\Models\TeacherPeriodEvaluation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluationCycleService
{
    /** أسماء حالات الدورة كما تظهر للمستخدم، حتى لا تصل الرموز البرمجية للواجهة. */
    private const STATUS_LABELS = [
        'draft' => 'إعداد',
        'data_collection' => 'جمع البيانات',
        'ready' => 'جاهزة',
        'calculated' => 'محسوبة',
        'approved' => 'معتمدة',
        'published' => 'منشورة',
        'archived' => 'مؤرشفة',
    ];

    public function __construct(
        private readonly EvaluationPolicyService $policies,
        private readonly EvaluationAuditService $audit,
    ) {}

    public function create(array $data, User $actor): EvaluationCycle
    {
        $periods = collect($data['periods'])->sortBy('sequence')->values();
        $this->validatePeriods($periods->all());
        $cycleStart = CarbonImmutable::parse($periods->min('start_date'))->toDateString();
        $cycleEnd = CarbonImmutable::parse($periods->max('end_date'))->toDateString();

        $courses = Course::query()
            ->whereIn('id', $data['course_ids'])
            ->get();
        if ($courses->count() !== count(array_unique($data['course_ids']))
            || $courses->contains(
                fn ($course) => (int) $course->project_id !== (int) $data['project_id']
            )) {
            throw ValidationException::withMessages([
                'course_ids' => ['كل المقررات المختارة يجب أن تنتمي إلى المشروع نفسه.'],
            ]);
        }

        return DB::transaction(function () use ($data, $actor, $periods, $cycleStart, $cycleEnd) {
            $policy = isset($data['rule_configuration'])
                ? $this->policies->createForCycle(
                    $data['rule_configuration'],
                    $data['name'],
                    (int) $data['project_id'],
                    $actor
                )
                : $this->policies->defaultPolicy($actor);
            $cycle = EvaluationCycle::create([
                'project_id' => $data['project_id'],
                'policy_id' => $policy->id,
                'name' => $data['name'],
                'season' => $data['season'] ?? 'winter',
                'start_date' => $cycleStart,
                'end_date' => $cycleEnd,
                'status' => 'draft',
                'top_students_count' => $data['top_students_count'] ?? 15,
                'created_by' => $actor->id,
            ]);
            $cycle->courses()->attach(array_unique($data['course_ids']));

            foreach ($periods as $period) {
                $cycle->periods()->create([
                    'name' => $period['name'],
                    'sequence' => $period['sequence'],
                    'start_date' => $period['start_date'],
                    'end_date' => $period['end_date'],
                    'status' => 'draft',
                ]);
            }

            $this->audit->record('evaluation.cycle_created', $cycle, $actor, null, $cycle->toArray());

            return $cycle->load(['project', 'policy', 'periods', 'courses']);
        });
    }

    /**
     * تصحيح تعريف الدورة قبل إغلاق جمع البيانات. كل حقل يحرس بحسب ما يعتمد عليه:
     * البيانات الوصفية حرة، والقواعد تنشئ نسخة سياسة جديدة بدل تعديل نسخة محفوظة،
     * والفترات والمقررات لا تتغير إذا كانت قد استقبلت مدخلات فعلية.
     */
    public function update(EvaluationCycle $cycle, array $data, User $actor): EvaluationCycle
    {
        if (! in_array($cycle->status, ['draft', 'data_collection'], true)) {
            $status = self::STATUS_LABELS[$cycle->status] ?? $cycle->status;
            throw ValidationException::withMessages([
                'cycle' => ["لا يمكن تعديل تعريف دورة في حالة «{$status}»؛ التعديل متاح قبل إغلاق جمع البيانات."],
            ]);
        }

        return DB::transaction(function () use ($cycle, $data, $actor) {
            $before = $cycle->load(['periods', 'courses'])->toArray();
            $attributes = array_intersect_key(
                $data,
                array_flip(['name', 'season', 'top_students_count'])
            );

            if (isset($data['course_ids'])) {
                $this->updateCourses($cycle, $data['course_ids']);
            }
            if (isset($data['periods'])) {
                $attributes += $this->updatePeriods($cycle, $data['periods']);
            }
            if (isset($data['rule_configuration'])) {
                // نسخة سياسة جديدة: النتائج المحفوظة تحمل لقطتها الخاصة فلا يتغير أثر رجعي.
                $attributes['policy_id'] = $this->policies->createForCycle(
                    $data['rule_configuration'],
                    $attributes['name'] ?? $cycle->name,
                    (int) $cycle->project_id,
                    $actor
                )->id;
            }

            $cycle->update($attributes);
            $cycle->refresh()->load(['project', 'policy', 'periods', 'courses']);
            $this->audit->record('evaluation.cycle_updated', $cycle, $actor, $before, $cycle->toArray());

            return $cycle;
        });
    }

    public function transition(EvaluationCycle $cycle, string $target, User $actor): EvaluationCycle
    {
        $allowed = [
            'draft' => ['data_collection'],
            'data_collection' => ['ready', 'draft'],
            'ready' => ['data_collection'],
        ];

        if (! in_array($target, $allowed[$cycle->status] ?? [], true)) {
            $from = self::STATUS_LABELS[$cycle->status] ?? $cycle->status;
            $to = self::STATUS_LABELS[$target] ?? $target;
            throw ValidationException::withMessages([
                'status' => ["لا يمكن نقل الدورة من حالة «{$from}» إلى حالة «{$to}»."],
            ]);
        }
        if ($target === 'data_collection' && $cycle->end_date->endOfDay()->isFuture()) {
            throw ValidationException::withMessages([
                'status' => ['لا يبدأ التقييم إلا بعد انتهاء آخر فترة واكتمال أيام الدوام الفعلية.'],
            ]);
        }

        $before = $cycle->toArray();
        $cycle->update(['status' => $target]);
        $this->audit->record('evaluation.cycle_status_changed', $cycle, $actor, $before, $cycle->toArray());

        return $cycle->fresh(['periods', 'courses']);
    }

    /**
     * الفترات تتطابق بالتسلسل: التسلسل الموجود يعدل، والجديد ينشأ، والغائب يحذف.
     * التسمية حرة دائمًا، أما التواريخ والحذف فمشروطان بخلو الفترة من المدخلات.
     *
     * @return array{start_date: string, end_date: string}
     */
    private function updatePeriods(EvaluationCycle $cycle, array $payload): array
    {
        $periods = collect($payload)->sortBy('sequence')->values();
        $this->validatePeriods($periods->all());

        $existing = $cycle->periods()->get()->keyBy(fn ($period) => (int) $period->sequence);
        $incoming = $periods->keyBy(fn ($period) => (int) $period['sequence']);

        foreach ($existing as $sequence => $period) {
            if (! $incoming->has($sequence)) {
                $this->assertPeriodIsUntouched($period, 'حذف');
                $period->delete();
            }
        }

        foreach ($incoming as $sequence => $period) {
            $current = $existing->get($sequence);
            if (! $current) {
                $cycle->periods()->create([
                    'name' => $period['name'],
                    'sequence' => $sequence,
                    'start_date' => $period['start_date'],
                    'end_date' => $period['end_date'],
                    'status' => 'draft',
                ]);

                continue;
            }

            $start = CarbonImmutable::parse($period['start_date'])->toDateString();
            $end = CarbonImmutable::parse($period['end_date'])->toDateString();
            if ($current->start_date->toDateString() !== $start || $current->end_date->toDateString() !== $end) {
                $this->assertPeriodIsUntouched($current, 'تعديل تواريخ');
            }
            $current->update([
                'name' => $period['name'],
                'start_date' => $start,
                'end_date' => $end,
            ]);
        }

        return [
            'start_date' => CarbonImmutable::parse($periods->min('start_date'))->toDateString(),
            'end_date' => CarbonImmutable::parse($periods->max('end_date'))->toDateString(),
        ];
    }

    private function assertPeriodIsUntouched(EvaluationPeriod $period, string $action): void
    {
        $touched = TeacherPeriodEvaluation::where('evaluation_period_id', $period->id)->exists()
            || QuranPeriodAssessment::where('evaluation_period_id', $period->id)->exists()
            || EvaluationExamResult::where('evaluation_period_id', $period->id)->exists();

        if ($touched) {
            throw ValidationException::withMessages([
                'periods' => ["لا يمكن {$action} الفترة «{$period->name}» بعد إدخال تقييمات مرتبطة بها."],
            ]);
        }
    }

    /**
     * إضافة المقررات حرة، ويعاد مزامنة المرشحين بعدها. أما الإزالة فترفض إذا كان
     * للمقرر مدخلات، وإلا حذفت لقطات تسجيله واستبعد من لم يبق له تسجيل في الدورة.
     */
    private function updateCourses(EvaluationCycle $cycle, array $courseIds): void
    {
        $courseIds = array_values(array_unique(array_map('intval', $courseIds)));
        $courses = Course::query()->whereIn('id', $courseIds)->get();
        if ($courses->count() !== count($courseIds)
            || $courses->contains(
                fn ($course) => (int) $course->project_id !== (int) $cycle->project_id
            )) {
            throw ValidationException::withMessages([
                'course_ids' => ['كل المقررات المختارة يجب أن تنتمي إلى المشروع نفسه.'],
            ]);
        }

        $removed = array_values(array_diff(
            $cycle->courses()->pluck('courses.id')->map(fn ($id) => (int) $id)->all(),
            $courseIds
        ));
        $candidateIds = $cycle->candidates()->pluck('id');
        foreach ($removed as $courseId) {
            $this->assertCourseIsUntouched($courseId, $candidateIds->all());
        }

        $cycle->courses()->sync($courseIds);

        if ($removed === []) {
            return;
        }

        EvaluationCandidateEnrollment::query()
            ->whereIn('evaluation_candidate_id', $candidateIds)
            ->whereIn('course_id', $removed)
            ->delete();
        // الاستبعاد لا الحذف: الاحتساب يتجاهل غير النشط، ويبقى أثر المرشح في السجل.
        EvaluationCandidate::query()
            ->whereIn('id', $candidateIds)
            ->whereDoesntHave('enrollments')
            ->update([
                'status' => 'excluded',
                'status_reason' => 'أزيل المقرر المرتبط من دورة التقييم.',
            ]);
    }

    private function assertCourseIsUntouched(int $courseId, array $candidateIds): void
    {
        $circleIds = EvaluationCandidateEnrollment::query()
            ->whereIn('evaluation_candidate_id', $candidateIds)
            ->where('course_id', $courseId)
            ->pluck('circle_id');

        $touched = TeacherPeriodEvaluation::query()
            ->whereIn('evaluation_candidate_id', $candidateIds)
            ->whereIn('circle_id', $circleIds)
            ->exists()
            || QuranPeriodAssessment::query()
                ->whereIn('evaluation_candidate_id', $candidateIds)
                ->where('course_id', $courseId)
                ->exists()
            || EvaluationExamResult::query()
                ->whereIn('evaluation_candidate_id', $candidateIds)
                ->where('course_id', $courseId)
                ->exists();

        if ($touched) {
            $name = Course::whereKey($courseId)->value('name') ?? $courseId;
            throw ValidationException::withMessages([
                'course_ids' => ["لا يمكن إزالة المقرر «{$name}» بعد إدخال تقييمات لطلابه."],
            ]);
        }
    }

    private function validatePeriods(array $periods): void
    {
        if ($periods === []) {
            throw ValidationException::withMessages(['periods' => ['يجب إنشاء فترة تقييم واحدة على الأقل.']]);
        }

        $previousEnd = null;
        foreach ($periods as $index => $period) {
            $start = CarbonImmutable::parse($period['start_date'])->startOfDay();
            $end = CarbonImmutable::parse($period['end_date'])->startOfDay();

            $number = $index + 1;
            if ($end->lessThan($start)) {
                throw ValidationException::withMessages([
                    "periods.{$index}" => ["الفترة رقم {$number}: يجب أن تكون نهاية الفترة مساوية لبدايتها أو لاحقة لها."],
                ]);
            }
            if ($previousEnd && $start->lessThanOrEqualTo($previousEnd)) {
                throw ValidationException::withMessages([
                    "periods.{$index}" => ["الفترة رقم {$number}: فترات التقييم يجب ألا تتداخل."],
                ]);
            }
            $previousEnd = $end;
        }
    }
}
