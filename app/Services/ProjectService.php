<?php

namespace App\Services;

use App\Exceptions\ProjectHasCoursesException;
use App\IService\IProjectService;
use App\Models\Course;
use App\Models\Project;
use App\Traits\FileTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

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

    public function getAllProjects(array $filters = [], $limit = null)
    {
        $query = Project::filter($filters)->orderBy('created_at', 'desc');

        return $limit ? $query->paginate($limit) : $query->get();
    }

    public function getProjectById(int $id): ?Project
    {
        return Project::find($id);
    }

    public function editProjectStatus(Project $project): Project
    {
        $project->is_active = ! $project->is_active;
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

        if (! $project) {
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
        // Assignment is the source of truth. Role names are presentation data;
        // authorization remains enforced by route permissions and assignment.
        return Project::where('supervisor', $userId)->get();
    }

    public function deleteProject(int $id): bool
    {
        $project = Project::findOrFail($id);

        $coursesCount = $project->courses()->count();
        if ($coursesCount > 0) {
            throw new ProjectHasCoursesException($coursesCount);
        }

        $logo = $project->logo;

        try {
            $deleted = $project->delete();
        } catch (QueryException $exception) {
            if ($this->isForeignKeyConstraintViolation($exception)) {
                throw new ProjectHasCoursesException(
                    $project->courses()->count(),
                    $exception,
                );
            }

            throw $exception;
        }

        // Delete the file only after the database row is deleted successfully.
        // Previously, a rejected database delete could leave the project
        // without its logo even though the project itself still existed.
        if ($deleted && $logo) {
            $this->deleteFile($logo);
        }

        return $deleted;
    }

    private function isForeignKeyConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23000' && $driverCode === 1451;
    }
}
