<?php

namespace App\Services;

use App\IService\IProjectService;
use App\Models\Project;
use App\Traits\FileTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use App\Models\Course;


class ProjectService implements IProjectService
{
    use FileTrait;

    public function createProject(array $data): Project
    {

        if (isset($data['logo']) && $data['logo'] instanceof \Illuminate\Http\UploadedFile) {
            $data['logo'] = $this->saveFile($data['logo'], 'projects/logos');
        }

        return Project::create($data);
    }


    public function updateProject(Project $project, array $data): Project
    {
        if (isset($data['logo']) && $data['logo'] instanceof \Illuminate\Http\UploadedFile) {

            if ($project->logo) {
                $this->deleteFile($project->logo);
            }

            $data['logo'] = $this->saveFile($data['logo'], 'projects/logos');
        }

        $project->update($data);

        return $project;
    }

    public function getAllProjects($status = null)
    {
        $query = Project::query();

        if (!is_null($status)) {
            $query->where('is_active', (bool)$status);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getProjectById(int $id): ?Project
    {
        return Project::find($id);
    }

    public function editProjectStatus(Project $project): Project
    {
        $project->is_active = !$project->is_active;
        $project->save();

        return $project;
    }

    public function getCurrentSupervisorProject(): ?Project
    {
        // جلب المشروع حيث supervisor يساوي ID المستخدم المسجل دخوله
        return Project::where('supervisor', Auth::id())->first();
    }

    public function getMyProjectCourses(): Collection
    {
        $project = $this->getCurrentSupervisorProject();

        if (!$project) {
            return collect();
        }


        return Course::where('project_id', $project->id)->get();
    }

    public function authorizeProjectAccess(int $projectId): bool
    {
        $myProject = $this->getCurrentSupervisorProject();
        return $myProject && $myProject->id === $projectId;
    }

    public function getMyProjects(int $userId)
    {
        $user = Auth::user();

        if ($user->hasRole('supervisor')) {
            return Project::where('supervisor', $userId)->get();
        }

        return collect();
    }

    public function deleteProject(int $id): bool
    {
        $project = Project::findOrFail($id);
        if ($project->logo) {
            $this->deleteFile($project->logo);
        }
        return $project->delete();
    }
}
