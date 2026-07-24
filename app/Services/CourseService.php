<?php

namespace App\Services;

use App\IService\ICourseService;
use App\Models\Course;
use App\Models\StudentCircle;
use Illuminate\Support\Facades\DB;

class CourseService implements ICourseService
{
    public function __construct(private readonly StudentSelfNumberService $selfNumberService) {}

    public function createCourse(array $data): Course
    {

        return Course::create($data);
    }

    public function getAllCourses(array $filters)
    {
        $query = Course::with(['mosque', 'project', 'parentCourse'])
            ->filter($filters)
            ->latest();

        return isset($filters['limit']) ? $query->paginate($filters['limit']) : $query->get();
    }

    public function getCourseById(int $id): ?Course
    {
        return Course::find($id);
    }

    public function updateCourse(Course $course, array $data): Course
    {
        $course->update($data);

        return $course->fresh();
    }

    public function deleteCourse(Course $course): bool
    {
        return DB::transaction(function () use ($course): bool {
            $studentIds = StudentCircle::query()
                ->whereHas('circleDetails', function ($query) use ($course): void {
                    $query->where('course_id', $course->id);
                })
                ->pluck('student')
                ->unique()
                ->values()
                ->all();

            $deleted = $course->delete();

            if ($deleted) {
                foreach ($studentIds as $studentId) {
                    $this->selfNumberService->deactivateIfUnenrolled((int) $studentId);
                }
            }

            return $deleted;
        }, 3);
    }

    public function editCourseStatus(Course $course): Course
    {
        $course->is_active = ! $course->is_active;
        $course->save();

        return $course;
    }

    public function getMyCourses(int $userId)
    {
        // جلب الكورسات بناءً على المعرف الممرر فقط
        return Course::where('supervisor_id', $userId)->get();
    }
}
