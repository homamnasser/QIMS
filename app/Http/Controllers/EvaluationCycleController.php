<?php

namespace App\Http\Controllers;

use App\Models\EvaluationCycle;
use App\Models\EvaluationPolicy;
use App\Models\Project;
use App\Services\Evaluation\EvaluationAccessService;
use App\Services\Evaluation\EvaluationCandidateSyncService;
use App\Services\Evaluation\EvaluationCycleService;
use App\Services\Evaluation\EvaluationPolicyService;
use App\Services\Evaluation\EvaluationRuleDefinitionService;
use App\Services\Evaluation\EvaluationRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EvaluationCycleController extends Controller
{
    public function __construct(
        private readonly EvaluationAccessService $access,
        private readonly EvaluationCycleService $cycles,
        private readonly EvaluationCandidateSyncService $candidates,
        private readonly EvaluationRunService $runs,
        private readonly EvaluationRuleDefinitionService $ruleDefinitions,
        private readonly EvaluationPolicyService $policies,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = EvaluationCycle::query()
            ->with(['project:id,name', 'policy:id,name,version'])
            ->withCount(['periods', 'courses', 'candidates']);
        $this->access->scopeVisibleCycles($query, $request->user());

        $cycles = $query
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->latest('start_date')
            ->paginate(min($request->integer('per_page', 15), 50));

        return response()->json($cycles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'season' => ['nullable', 'string', 'in:winter,summer,annual,custom'],
            'top_students_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'distinct', 'exists:courses,id'],
            'periods' => ['required', 'array', 'min:1'],
            'periods.*.name' => ['required', 'string', 'max:255'],
            'periods.*.sequence' => ['required', 'integer', 'min:1', 'distinct'],
            'periods.*.start_date' => ['required', 'date'],
            'periods.*.end_date' => ['required', 'date'],
            'rule_configuration' => ['nullable', 'array'],
        ]);

        $project = Project::findOrFail($data['project_id']);
        abort_unless($this->access->canConfigureProject($request->user(), $project), 403);

        return response()->json([
            'message' => 'تم إنشاء دورة التقييم.',
            'data' => $this->cycles->create($data, $request->user()),
        ], 201);
    }

    public function update(Request $request, EvaluationCycle $cycle): JsonResponse
    {
        abort_unless($this->access->canManageCycle($request->user(), $cycle), 403);

        // المشروع غير قابل للتغيير: تغييره يبطل المقررات والمرشحين ولقطات تسجيلهم.
        $data = $request->validate([
            'name' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('evaluation_cycles')
                    ->where(fn ($query) => $query->where('project_id', $cycle->project_id))
                    ->ignore($cycle->id),
            ],
            'season' => ['sometimes', 'string', 'in:winter,summer,annual,custom'],
            'top_students_count' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'course_ids' => ['sometimes', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'distinct', 'exists:courses,id'],
            'periods' => ['sometimes', 'array', 'min:1'],
            'periods.*.name' => ['required', 'string', 'max:255'],
            'periods.*.sequence' => ['required', 'integer', 'min:1', 'distinct'],
            'periods.*.start_date' => ['required', 'date'],
            'periods.*.end_date' => ['required', 'date'],
            'rule_configuration' => ['sometimes', 'array'],
        ]);

        return response()->json([
            'message' => 'تم تحديث تعريف دورة التقييم.',
            'data' => $this->cycles->update($cycle, $data, $request->user()),
        ]);
    }

    /**
     * القالب الفارغ افتراضاً، أو محمّلاً بقواعد دورة قائمة («cycle») أو بقواعد
     * قالب محفوظ («profile»)، ليبدأ المستخدم من إعداد جاهز بدل بنائه من الصفر.
     */
    public function ruleTemplate(Request $request): JsonResponse
    {
        if ($cycleId = $request->integer('cycle')) {
            $cycle = EvaluationCycle::with('policy')->findOrFail($cycleId);
            abort_unless($this->access->canViewCycle($request->user(), $cycle), 403);

            return response()->json([
                'data' => $this->ruleDefinitions->templateFromRules(
                    $cycle->policy?->configuration['criteria_rules'] ?? []
                ),
            ]);
        }

        if ($profileId = $request->integer('profile')) {
            $profile = EvaluationPolicy::where('status', 'template')->findOrFail($profileId);

            return response()->json([
                'data' => $this->ruleDefinitions->templateFromRules(
                    $profile->configuration['criteria_rules'] ?? []
                ),
            ]);
        }

        return response()->json(['data' => $this->ruleDefinitions->template()]);
    }

    public function ruleProfiles(): JsonResponse
    {
        return response()->json(['data' => $this->policies->templates()]);
    }

    public function storeRuleProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rule_configuration' => ['required', 'array'],
        ]);

        return response()->json([
            'message' => 'تم حفظ قالب القواعد.',
            'data' => $this->policies->saveTemplate(
                $data['name'],
                $data['rule_configuration'],
                $request->user()
            ),
        ], 201);
    }

    public function show(Request $request, EvaluationCycle $cycle): JsonResponse
    {
        abort_unless($this->access->canViewCycle($request->user(), $cycle), 403);

        return response()->json([
            'data' => $cycle->load([
                'project:id,name',
                'policy:id,name,version,status,configuration',
                'periods',
                'courses:id,name,project_id,mosque_id,supervisor_id',
                'candidates.student:id,first_name,last_name,selfnumber,academic_class,reading_level',
                'candidates.enrollments',
                'latestFinalRun',
                'runs' => fn ($query) => $query->latest('sequence')->limit(5),
            ])->loadCount('candidates'),
        ]);
    }

    public function syncCandidates(Request $request, EvaluationCycle $cycle): JsonResponse
    {
        abort_unless($this->access->canManageCycle($request->user(), $cycle), 403);

        return response()->json([
            'message' => 'تمت مزامنة المرشحين وتجميد بيانات تسجيلهم.',
            'data' => $this->candidates->sync($cycle),
        ]);
    }

    public function readiness(Request $request, EvaluationCycle $cycle): JsonResponse
    {
        abort_unless($this->access->canViewCycle($request->user(), $cycle), 403);

        return response()->json(['data' => $this->runs->readiness($cycle)]);
    }

    public function transition(Request $request, EvaluationCycle $cycle): JsonResponse
    {
        abort_unless($this->access->canManageCycle($request->user(), $cycle), 403);
        $data = $request->validate([
            'status' => ['required', 'string', 'in:draft,data_collection,ready'],
        ]);

        if ($data['status'] === 'ready') {
            $readiness = $this->runs->readiness($cycle);
            if (! $readiness['is_ready']) {
                // تفاصيل الجاهزية تُعاد في data لا في errors، لأن errors مخصص
                // لأخطاء الحقول التي تعرضها الواجهة أسفل المدخلات.
                return response()->json([
                    'code' => 422,
                    'error_code' => 'CYCLE_NOT_READY',
                    'message' => sprintf(
                        'لا يمكن إغلاق جمع البيانات قبل اكتمال المدخلات: %d من %d طالب جاهز.',
                        $readiness['ready_candidate_count'],
                        $readiness['candidate_count'],
                    ),
                    'data' => ['readiness' => $readiness],
                ], 422);
            }
        }

        return response()->json([
            'message' => 'تم تحديث حالة دورة التقييم.',
            'data' => $this->cycles->transition($cycle, $data['status'], $request->user()),
        ]);
    }
}
