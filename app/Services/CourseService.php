<?php

namespace App\Services;

use App\Models\Course;
use Exception;
use App\IService\ICourseService;

class CourseService implements ICourseService
{
    public function createCourse(array $data): Course
    {

        return Course::create($data);
    }

    public function getAllCourses(array $filters)
    {
        return Course::with(['mosque', 'project', 'parentCourse'])
            ->filter($filters)
            ->latest()
            ->get();
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
        return $course->delete();
    }

    public function editCourseStatus(Course $course): Course
    {
        $course->is_active = !$course->is_active;
        $course->save();

        return $course;
    }
}
