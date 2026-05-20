<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\StaffService;
use App\Services\ProjectService;
use App\IService\IStaffService;
use App\IService\IProjectService;
use App\IService\IRoleService;
use App\Services\RoleService;
use Illuminate\Support\Facades\Gate;
use App\Services\MosqueService;
use App\IService\IMosqueService;
use App\IService\ICourseService;
use App\Services\CourseService;
use App\IService\ISubjectService;
use App\Services\SubjectService;
use App\IService\ILessonService;
use App\Services\LessonService;
use App\IService\ICourseDateService;
use App\Services\CourseDateService;
use App\IService\ICourseCurriculumService;
use App\Services\CourseCurriculumService;
use App\Services\CircleService;
use App\IService\ICircleService;
use App\IService\IStudentService;
use App\Services\StudentService;
use App\IService\IStudentCircleService;
use App\Services\StudentCircleService;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(IStaffService::class, StaffService::class);
        $this->app->bind(IRoleService::class, RoleService::class);
        $this->app->bind(IProjectService::class, ProjectService::class);
        $this->app->bind(IMosqueService::class, MosqueService::class);
        $this->app->bind(ICourseService::class, CourseService::class);
        $this->app->bind(ISubjectService::class, SubjectService::class);
        $this->app->bind(ILessonService::class, LessonService::class);
        $this->app->bind(ICourseDateService::class, CourseDateService::class);
        $this->app->bind(ICourseCurriculumService::class, CourseCurriculumService::class);
        $this->app->bind(ICircleService::class, CircleService::class);
        $this->app->bind(IStudentService::class, StudentService::class);
        $this->app->bind(IStudentCircleService::class, StudentCircleService::class);
    }


    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
