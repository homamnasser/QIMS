<?php

namespace App\Services\Evaluation;

use App\Models\AdministrationBehaviorObservation;
use App\Models\Certificate;
use App\Models\EvaluationAuditEvent;
use App\Models\EvaluationCandidate;
use App\Models\EvaluationCriterionResult;
use App\Models\EvaluationCycle;
use App\Models\EvaluationExamResult;
use App\Models\EvaluationResult;
use App\Models\EvaluationRun;
use App\Models\QuranPeriodAssessment;
use App\Models\ReadingImprovement;
use App\Models\RecognitionBatch;
use App\Models\TeacherPeriodEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EvaluationAuditService
{
    public function record(
        string $eventType,
        Model $auditable,
        ?User $actor,
        ?array $before = null,
        ?array $after = null,
        array $context = []
    ): EvaluationAuditEvent {
        $request = request();

        return EvaluationAuditEvent::create([
            'evaluation_cycle_id' => $this->cycleId($auditable, $context),
            'event_type' => $eventType,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? 'user' : 'system',
            'before' => $before,
            'after' => $after,
            'context' => $context,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'occurred_at' => now(),
        ]);
    }

    private function cycleId(Model $auditable, array $context): ?int
    {
        if (isset($context['evaluation_cycle_id'])) {
            return (int) $context['evaluation_cycle_id'];
        }

        return match (true) {
            $auditable instanceof EvaluationCycle => $auditable->id,
            $auditable instanceof EvaluationCandidate => $auditable->evaluation_cycle_id,
            $auditable instanceof EvaluationRun => $auditable->evaluation_cycle_id,
            $auditable instanceof RecognitionBatch => $auditable->evaluation_cycle_id,
            $auditable instanceof EvaluationResult => $auditable->run()->value('evaluation_cycle_id'),
            $auditable instanceof EvaluationCriterionResult => $auditable->result
                ? $auditable->result->run()->value('evaluation_cycle_id')
                : null,
            $auditable instanceof Certificate => $auditable->result
                ? $auditable->result->run()->value('evaluation_cycle_id')
                : null,
            $auditable instanceof AdministrationBehaviorObservation => $auditable->candidate
                ? $auditable->candidate->evaluation_cycle_id
                : null,
            $auditable instanceof TeacherPeriodEvaluation,
            $auditable instanceof QuranPeriodAssessment,
            $auditable instanceof EvaluationExamResult => $auditable->candidate
                ? $auditable->candidate->evaluation_cycle_id
                : null,
            $auditable instanceof ReadingImprovement => $auditable->evaluationCandidate
                ? $auditable->evaluationCandidate->evaluation_cycle_id
                : null,
            default => null,
        };
    }
}
