<?php

namespace App\Services\Evaluation;

use App\Models\EvaluationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluationWorkflowService
{
    public function __construct(
        private readonly RecognitionService $recognition,
        private readonly EvaluationAuditService $audit,
    ) {}

    public function approve(EvaluationRun $run, User $actor): EvaluationRun
    {
        if ($run->is_preview || $run->status !== 'completed') {
            throw ValidationException::withMessages([
                'run' => ['يمكن اعتماد تشغيل نهائي مكتمل فقط.'],
            ]);
        }

        return DB::transaction(function () use ($run, $actor) {
            $run->loadMissing('cycle');
            $run->results()->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ]);
            $run->cycle->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ]);

            $this->recognition->generate($run, $actor);
            $this->audit->record('evaluation.results_approved', $run, $actor);

            return $run->fresh(['results.criteria', 'cycle']);
        });
    }

    public function publish(EvaluationRun $run, User $actor): EvaluationRun
    {
        $run->loadMissing('cycle');
        if ($run->is_preview || $run->cycle->status !== 'approved') {
            throw ValidationException::withMessages([
                'run' => ['يجب اعتماد النتائج النهائية قبل نشرها.'],
            ]);
        }

        return DB::transaction(function () use ($run, $actor) {
            $run->results()->where('status', 'approved')->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
            $run->cycle->update([
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $actor->id,
            ]);
            $this->audit->record('evaluation.results_published', $run, $actor);

            return $run->fresh(['results.criteria', 'cycle']);
        });
    }
}
