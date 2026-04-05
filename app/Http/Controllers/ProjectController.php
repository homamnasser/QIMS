<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\IService\IProjectService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;

class ProjectController extends Controller
{
    protected $projectService;

    public function __construct(IProjectService $projectService)
    {
        $this->projectService = $projectService;
    }


    public function createProject(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->createProject($request->validated());

        return response()->json([
            'code'    => 201,
            'status'  => 'success',
            'message' => 'Project created successfully.',
            'data'    => new ProjectResource($project)
        ], 201);
    }


    public function updateProject(UpdateProjectRequest $request, string $id): JsonResponse
    {

        $project = Project::findOrFail($id);

        $updatedProject = $this->projectService->updateProject($project, $request->validated());

        return response()->json([
            'code'    => 200,
            'message' => 'Project updated successfully.',
            'data'    => new ProjectResource($updatedProject)
        ], 200);
    }
}
