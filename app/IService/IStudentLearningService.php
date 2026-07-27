<?php

namespace App\IService;

use App\Models\Course;
use App\Models\Mosque;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

interface IStudentLearningService
{
    public function getMosque(Student $student): ?Mosque;

    public function getCircles(Student $student): Collection;

    public function getCourses(Student $student): Collection;

    public function getCourseSchedule(Student $student, int $courseId): ?Course;

    public function getNotes(Student $student): Collection;

    public function getSabrs(Student $student): Collection;

    public function getMemorizations(Student $student): Collection;

    public function getWarnings(Student $student): Collection;

    public function getExams(Student $student): Collection;
}
