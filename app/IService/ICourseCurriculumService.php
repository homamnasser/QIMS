<?php

namespace App\IService;

use App\Models\CourseDate;

interface ICourseCurriculumService
{
    public function assignLessonsToDate(CourseDate $courseDate, array $lessonIds);
    public function updateCourseCurriculum(CourseDate $courseDate, array $lessonIds);
    public function detachAllLessons(int $courseDateId): bool;
    public function getCourseDateWithLessons(int $id);
    public function getFullCourseCurriculum(int $courseId);
}
