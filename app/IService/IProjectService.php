<?php

namespace App\IService;

use App\Models\Project;
use Illuminate\Support\Collection;

interface IProjectService
{
    public function createProject(array $data): Project;
    public function updateProject(Project $project, array $data): Project;
    public function getAllProjects($status = null);
    public function getProjectById(int $id): ?Project;
    public function editProjectStatus(Project $project): Project;
}
