<?php

namespace App\Services;

use App\IService\IProjectService;
use App\Models\Project;
use App\Traits\FileTrait;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;


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
}
