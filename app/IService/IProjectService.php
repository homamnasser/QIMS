<?php

namespace App\IService;

use App\Models\Project;
use Illuminate\Support\Collection;

interface IProjectService
{
    public function createProject(array $data): Project;

    public function updateProject(Project $project, array $data): Project;

    public function getAllProjects(array $filters = [], $limit = null);

    public function getProjectById(int $id): ?Project;

    public function editProjectStatus(Project $project): Project;

    public function authorizeProjectAccess(int $projectId): bool;

    public function getMyProjectCourses(): Collection;

    public function getCurrentSupervisorProject(): ?Project;

    public function getMyProjects(int $userId);

    public function deleteProject(int $id): bool;
}
