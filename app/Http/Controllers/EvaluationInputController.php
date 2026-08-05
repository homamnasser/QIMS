<?php

namespace App\Http\Controllers;

use App\Models\EvaluationCandidate;
use App\Models\TeacherPeriodEvaluation;
use App\Services\Evaluation\EvaluationAccessService;
use App\Services\Evaluation\EvaluationCalculator;
use App\Services\Evaluation\EvaluationInputService;
use App\Services\Evaluation\EvaluationPolicyService;
use App\Services\Evaluation\EvaluationSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationInputController extends Controller
{
    public function __construct(
        private readonly EvaluationAccessService $access,
        private readonly EvaluationInputService $inputs,
        private readonly EvaluationCalculator $calculator,
        private readonly EvaluationPolicyService $policies,
        private readonly EvaluationSourceService $sources,
    ) {}

    public function teacherCandidates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cycle_id' => ['nullable', 'integer', 'exists:evaluation_cycles,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $user = $request->user();
        $teacherId = $user->id;
        $hasFullFieldAccess = $user->hasFullFieldOperationsAccess();
        $perPage = (int) ($data['per_page'] ?? 20);

        $candidates = EvaluationCandidate::query()
            ->with([
                'cycle:id,name,status,start_date,end_date',
                'student:id,first_name,last_name,selfnumber,academic_class,reading_level',
                'enrollments' => fn ($query) => $query
                    ->when(
                        ! $hasFullFieldAccess,
                        fn ($enrollment) => $enrollment->where('teacher_id', $teacherId)
                    )
                    ->orderBy('circle_name_snapshot'),
            ])
            ->where('status', 'active')
            ->when(
                ! $hasFullFieldAccess,
                fn ($query) => $query->whereHas(
                    'enrollments',
                    fn ($enrollment) => $enrollment->where('teacher_id', $teacherId)
                )
            )
            ->when(
                isset($data['cycle_id']),
                fn ($query) => $query->where('evaluation_cycle_id', $data['cycle_id'])
            )
            ->when(isset($data['search']), function ($query) use ($data): void {
                $search = trim($data['search']);
                $query->whereHas('student', fn ($student) => $student
                    ->where(function ($name) use ($search): void {
                        $name->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('selfnumber', 'like', "%{$search}%");
                    }));
            })
            ->latest('evaluation_cycle_id')
            ->orderBy('student_id')
            ->paginate($perPage);

        return response()->json([
            'data' => $candidates->items(),
            'meta' => [
                'current_page' => $candidates->currentPage(),
                'last_page' => $candidates->lastPage(),
                'per_page' => $candidates->perPage(),
                'total' => $candidates->total(),
            ],
        ]);
    }

    public function review(Request $request, EvaluationCandidate $candidate): JsonResponse
    {
        abort_unless($this->access->canEvaluateCandidate($request->user(), $candidate), 403);
        $candidate->load([
            'cycle.policy',
            'cycle.periods',
            'enrollments',
            'student:id,first_name,last_name,selfnumber,academic_class,reading_level',
        ]);
        $policy = $this->policies->configuration($candidate->cycle->policy);
        $calculation = $this->calculator->calculate($candidate, $policy);
        $teacherEvaluations = TeacherPeriodEvaluation::query()
            ->where('evaluation_candidate_id', $candidate->id)
            ->get()
            ->keyBy(fn ($evaluation) => $evaluation->evaluation_period_id.'|'.$evaluation->circle_id);
        $accessibleCircleIds = $candidate->enrollments
            ->filter(fn ($enrollment) => $this->access->canEvaluateCandidate(
                $request->user(),
                $candidate,
                $enrollment->circle_id
            ))
            ->pluck('circle_id')
            ->map(fn ($circleId) => (int) $circleId);

        $teacherRequirements = [];
        foreach ($candidate->cycle->periods as $period) {
            foreach ($candidate->enrollments as $enrollment) {
                if (! $this->access->canEvaluateCandidate(
                    $request->user(),
                    $candidate,
                    $enrollment->circle_id
                )) {
                    continue;
                }
                $key = $period->id.'|'.$enrollment->circle_id;
                $existing = $teacherEvaluations->get($key);
                $teacherRequirements[] = [
                    'evaluation_period_id' => $period->id,
                    'period_name' => $period->name,
                    'circle_id' => $enrollment->circle_id,
                    'circle_name' => $enrollment->circle_name_snapshot,
                    'existing_evaluation' => $existing,
                    'evidence' => $this->sources->teacherEvidence(
                        $candidate,
                        $enrollment,
                        $period
                    ),
                ];
            }
        }

        $criteria = collect($calculation['criteria'])->keyBy('key');
        $quranRequirements = collect(
            $criteria->get('quran')['inputs']['manual_flag_required'] ?? []
        )->filter(fn (array $requirement) => $accessibleCircleIds
            ->contains((int) $requirement['circle_id']))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'candidate' => $candidate,
                'calculation' => $calculation,
                'data_sources' => [
                    'attendance' => 'student_course_absences + course_dates',
                    'reading' => 'reading_improvements',
                    'quran' => 'memorizations',
                    'theoretical_exams' => 'exams + subjects.max_marks',
                    'administration_evaluation' => 'warnings.deduction_points',
                    'sabr_bonus' => 'sabrs',
                ],
                'manual_requirements' => [
                    'teacher_evaluations' => $teacherRequirements,
                    'quran_below_minimum' => $quranRequirements,
                ],
            ],
        ]);
    }

    public function teacher(Request $request, EvaluationCandidate $candidate): JsonResponse
    {
        $data = $request->validate([
            'evaluation_period_id' => ['required', 'integer', 'exists:evaluation_periods,id'],
            'circle_id' => ['required', 'integer', 'exists:circles,id'],
            'behavior_score' => ['required', 'numeric', 'min:0'],
            'participation_score' => ['required', 'numeric', 'min:0'],
            'teacher_opinion_score' => ['required', 'numeric', 'min:0'],
            'comments' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', 'string', 'in:draft,submitted'],
        ]);
        abort_unless(
            $this->access->canEvaluateCandidate($request->user(), $candidate, $data['circle_id']),
            403
        );

        return $this->saved(
            $this->inputs->saveTeacher($candidate, $data, $request->user()),
            'تم حفظ تقييم المدرس.'
        );
    }

    public function quran(Request $request, EvaluationCandidate $candidate): JsonResponse
    {
        $data = $request->validate([
            'evaluation_period_id' => ['required', 'integer', 'exists:evaluation_periods,id'],
            'circle_id' => ['required', 'integer', 'exists:circles,id'],
            'below_minimum' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        abort_unless(
            $this->access->canEvaluateCandidate($request->user(), $candidate, $data['circle_id']),
            403
        );

        return $this->saved(
            $this->inputs->saveQuran($candidate, $data, $request->user()),
            'تم حفظ تأكيد حالة التسميع؛ أعداد الصفحات والمراجعة مستخرجة آليًا.'
        );
    }
}
