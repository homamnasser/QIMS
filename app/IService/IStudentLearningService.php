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

    public function getReadingImprovements(Student $student): Collection;

    public function getAttendance(Student $student): Collection;

    public function getNotifications(Student $student): Collection;

    public function markNotificationsRead(Student $student): int;
}
