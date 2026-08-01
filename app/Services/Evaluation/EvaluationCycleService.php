<?php

namespace App\Services\Evaluation;

use App\Models\Course;
use App\Models\EvaluationCycle;
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
