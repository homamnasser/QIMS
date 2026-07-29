<?php

namespace App\Http\Controllers;

use App\Models\EvaluationCycle;
use App\Models\RecognitionBatch;
use App\Services\Evaluation\EvaluationAccessService;
use App\Services\Evaluation\EvaluationAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RecognitionController extends Controller
{
    public function __construct(
        private readonly EvaluationAccessService $access,
        private readonly EvaluationAuditService $audit,
    ) {}

    public function show(Request $request, EvaluationCycle $cycle): JsonResponse
    {
        abort_unless($this->access->canViewCycle($request->user(), $cycle), 403);
        $batch = RecognitionBatch::query()
            ->where('evaluation_cycle_id', $cycle->id)
            ->latest('id')
            ->with([
                'awards.result.candidate.student:id,first_name,last_name,selfnumber',
                'run:id,evaluation_cycle_id,sequence',
            ])
            ->first();

        return response()->json(['data' => $batch]);
    }

    public function approve(Request $request, RecognitionBatch $batch): JsonResponse
    {
        $batch->loadMissing('cycle');
        abort_unless($this->access->canApproveCycle($request->user(), $batch->cycle), 403);
        if ($batch->status !== 'draft') {
            throw ValidationException::withMessages([
                'batch' => ['يمكن اعتماد قائمة تكريم مسودة فقط.'],
            ]);
        }

        $batch->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        $this->audit->record('evaluation.recognition_approved', $batch, $request->user());

        return response()->json([
            'message' => 'تم اعتماد قائمة التكريم.',
            'data' => $batch->fresh('awards'),
        ]);
    }

    public function publish(Request $request, RecognitionBatch $batch): JsonResponse
    {
        $batch->loadMissing('cycle');
        abort_unless($this->access->canApproveCycle($request->user(), $batch->cycle), 403);
        if ($batch->status !== 'approved') {
            throw ValidationException::withMessages([
                'batch' => ['يجب اعتماد قائمة التكريم قبل نشرها.'],
            ]);
        }

        $batch->update(['status' => 'published', 'published_at' => now()]);
        $this->audit->record('evaluation.recognition_published', $batch, $request->user());

        return response()->json([
            'message' => 'تم نشر قائمة التكريم.',
            'data' => $batch->fresh('awards'),
        ]);
    }
}
