<?php
namespace App\Services;

use App\IService\IProjectService;
use App\Models\Project;
use App\Traits\FileTrait;
use Illuminate\Support\Str;

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
}
