<?php

namespace App\Http\Controllers;

use App\Models\EvaluationCycle;
use App\Models\EvaluationRun;
use App\Services\Evaluation\EvaluationAccessService;
use App\Services\Evaluation\EvaluationRunService;
use App\Services\Evaluation\EvaluationWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationRunController extends Controller
{
    public function __construct(
        private readonly EvaluationAccessService $access,
        private readonly EvaluationRunService $runs,
        private readonly EvaluationWorkflowService $workflow,
    ) {}

    public function store(Request $request, EvaluationCycle $cycle): JsonResponse
    {
        abort_unless($this->access->canManageCycle($request->user(), $cycle), 403);
        $data = $request->validate([
            'preview' => ['required', 'boolean'],
        ]);
        if (! $data['preview'] && $cycle->status !== 'ready') {
            return response()->json([
                'code' => 422,
                'error_code' => 'CYCLE_NOT_READY',
                'message' => 'يجب إغلاق جمع البيانات ووضع الدورة في حالة «جاهزة» قبل الاحتساب النهائي.',
                'data' => ['cycle_status' => $cycle->status],
            ], 422);
        }

        return response()->json([
            'message' => $data['preview']
                ? 'تم إنشاء معاينة جديدة للنتائج.'
                : 'تم احتساب النتائج النهائية وتجميد مدخلاتها.',
            'data' => $this->runs->run($cycle, $request->user(), $data['preview']),
        ], 201);
    }

    public function show(Request $request, EvaluationRun $run): JsonResponse
    {
        $run->loadMissing('cycle');
        abort_unless($this->access->canViewCycle($request->user(), $run->cycle), 403);

        return response()->json([
            'data' => $run->load([
                'cycle:id,name,status,project_id',
                'results.candidate.student:id,first_name,last_name,selfnumber',
                'results.criteria',
                'results.certificates',
            ]),
        ]);
    }

    public function approve(Request $request, EvaluationRun $run): JsonResponse
    {
        $run->loadMissing('cycle');
        abort_unless($this->access->canApproveCycle($request->user(), $run->cycle), 403);

        return response()->json([
            'message' => 'تم اعتماد النتائج وإنشاء قائمة التكريم.',
            'data' => $this->workflow->approve($run, $request->user()),
        ]);
    }

    public function publish(Request $request, EvaluationRun $run): JsonResponse
    {
        $run->loadMissing('cycle');
        abort_unless($this->access->canApproveCycle($request->user(), $run->cycle), 403);

        return response()->json([
            'message' => 'تم نشر النتائج وأصبحت متاحة للطلاب.',
            'data' => $this->workflow->publish($run, $request->user()),
        ]);
    }
}
