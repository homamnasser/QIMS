<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\IService\IProjectService;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{
    protected $projectService;

    public function __construct(IProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    /* إنشاء مشروع جديد */
    public function createProject(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->createProject($request->validated());

        return response()->json([
            'code' => 201,
            'message' => 'تم إنشاء المشروع بنجاح.',
            'data' => new ProjectResource($project->fresh()),
        ], 201);
    }

    /* تحديث مشروع (مع التأكد من وجوده أولاً) */
    public function updateProject(Request $request, int $id): JsonResponse
    {
        $project = $this->projectService->getProjectById((int) $id);

        if (! $project) {
            return response()->json([
                'code' => 404,
                'message' => 'المشروع غير موجود.',
                'data' => null,
            ], 404);
        }

        if (! $project->is_active) {
            return response()->json([
                'code' => 403,
                'message' => 'هذا المشروع مؤرشف ولا يمكن تعديله.',
            ], 403);
        }

        $projectRequest = app(UpdateProjectRequest::class);

        $validator = Validator::make(
            $request->all(),
            $projectRequest->rules(),
            $projectRequest->messages()
        );

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();
        $updatedProject = $this->projectService->updateProject($project, $validatedData);

        return response()->json([
            'code' => 200,
            'message' => 'تم تحديث بيانات المشروع بنجاح.',
            'data' => new ProjectResource($updatedProject),
        ], 200);
    }

    /* جلب قائمة المشاريع مع دعم الفلترة والبحث */
    public function getAllProjects(Request $request): JsonResponse
    {
        $filters = [
            'name' => $request->query('name') ?? $request->query('search'),
            'supervisor' => $request->query('supervisor'),
            'audience' => $request->query('audience'),
            'active' => $request->has('active')
                ? filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN)
                : null,
        ];
        $limit = $request->query('limit');

        $projects = $this->projectService->getAllProjects($filters, $limit);

        if ($projects->isEmpty()) {
            return response()->json([
                'code' => 200,
                'message' => 'لم يتم العثور على مشاريع.',
                'data' => [],
            ], 200);
        }

        $resource = ProjectResource::collection($projects)->response()->getData(true);

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب المشاريع بنجاح.',
            'data' => $resource['data'],
            'meta' => $resource['meta'] ?? null,
            'links' => $resource['links'] ?? null,
        ], 200);
    }

    /* جلب جميع الفئات المستهدفة المتاحة للمشاريع */
    public function getAudiences(): JsonResponse
    {
        $audiences = Project::query()
            ->whereNotNull('audience')
            ->where('audience', '!=', '')
            ->distinct()
            ->pluck('audience')
            ->values();

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب الفئات المستهدفة بنجاح.',
            'data' => $audiences,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function getProject($id)
    {
        $project = $this->projectService->getProjectById($id);

        if (! $project) {
            return response()->json([
                'code' => 404,
                'message' => 'المشروع غير موجود.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب بيانات المشروع بنجاح',
            'data' => new ProjectResource($project),
        ], 200);
    }

    public function editProjectStatus(Request $request, $id)
    {
        $project = $this->projectService->getProjectById($id);

        if (! $project) {
            return response()->json([
                'code' => 404,
                'message' => 'المشروع غير موجود.',
                'data' => null,
            ], 404);
        }

        $updatedProject = $this->projectService->editProjectStatus($project);

        return response()->json([
            'code' => 200,
            'message' => $updatedProject->is_active ? 'تم تفعيل المشروع' : 'تم أرشفة المشروع',
            'data' => new ProjectResource($updatedProject),
        ], 200);
    }

    /* حذف مشروع نهائياً */
    public function deleteProject(int $id): JsonResponse
    {
        $project = $this->projectService->getProjectById($id);

        if (! $project) {
            return response()->json([
                'code' => 404,
                'message' => 'المشروع غير موجود.',
                'data' => null,
            ], 404);
        }

        $this->projectService->deleteProject($id);

        return response()->json([
            'code' => 200,
            'message' => 'تم حذف المشروع بنجاح.',
            'data' => null,
        ], 200);
    }

    public function getMyProjects()
    {
        $userId = Auth::id();
        $projects = $this->projectService->getMyProjects($userId);

        // تحويل الـ Collection إلى Resource Collection
        // حتى لو كانت فارغة، سيرجع مصفوفة JSON نظيفة []
        return response()->json([
            'code' => 200,
            'message' => 'تم جلب المشاريع بنجاح.',
            'data' => ProjectResource::collection($projects),
        ], 200);
    }
}
