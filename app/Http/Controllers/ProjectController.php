<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\IService\IProjectService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\Request;
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
            'code'    => 201,
            'message' => 'Project created successfully.',
            'data'    => new ProjectResource($project->fresh())
        ], 201);
    }

    /* تحديث مشروع (مع التأكد من وجوده أولاً) */
    public function updateProject(Request $request, int $id): JsonResponse
    {
        $project = $this->projectService->getProjectById((int)$id);

        if (!$project) {
            return response()->json([
                'code'    => 404,
                'message' => 'Project not found',
                'data'    => null
            ], 404);
        }

        if (!$project->is_active) {
            return response()->json([
                'code'    => 403,
                'message' => 'This project is archived and cannot be modified.'
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
                'code'    => 422,
                'message' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();
        $updatedProject = $this->projectService->updateProject($project, $validatedData);

        return response()->json([
            'code'    => 200,
            'message' => 'Project updated successfully.',
            'data'    => new ProjectResource($updatedProject)
        ], 200);
    }

    /* الحصول على مشروع معين (مع التأكد من وجوده أولاً) */
    public function getAllProjects(Request $request): JsonResponse
    {
        $status = $request->has('active')
            ? filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN)
            : null;

        $projects = $this->projectService->getAllProjects($status);

        if ($projects->isEmpty()) {
            return response()->json([
                'code'    => 200,
                'message' => 'No projects found.',
                'data'    => []
            ], 200);
        }

        return response()->json([
            'code'    => 200,
            'message' => is_null($status) ? 'All projects retrieved.' : 'Projects filtered by status.',
            'data'    => ProjectResource::collection($projects)
        ], 200);
    }
    /* الحصول على مشروع معين (مع التأكد من وجوده أولاً) */
    public function getProject(int $id): JsonResponse
    {
        $project = $this->projectService->getProjectById($id);

        if (!$project) {
            return response()->json([
                'code'    => 404,
                'message' => 'Project not found',
                'data'    => null
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Project retrieved successfully',
            'data'    => new ProjectResource($project)
        ], 200);
    }
    /* حذف مشروع (مع التأكد من وجوده أولاً) */
    public function editProjectStatus(int $id): JsonResponse
    {
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'code'    => 404,
                'message' => 'Project not found'
            ], 404);
        }

        $updatedProject = $this->projectService->editProjectStatus($project);

        return response()->json([
            'code'    => 200,
            'message' => $updatedProject->is_active ? 'Project activated' : 'Project archived',
            'data'    => new ProjectResource($updatedProject)
        ], 200);
    }
}
