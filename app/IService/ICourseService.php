<?php
namespace App\IService;

use App\Models\Course;

interface ICourseService
{
    /**
     * إنشاء كورس جديد
     * @param array $data
     * @return Course
     */
    public function createCourse(array $data): Course;
    public function getAllCourses(array $filters);
    public function getCourseById(int $id): ?Course;
    public function updateCourse(Course $course, array $data): Course;
    public function deleteCourse(Course $course): bool;
    public function editCourseStatus(Course $course): Course;
}
