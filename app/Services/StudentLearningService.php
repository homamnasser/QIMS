<?php

namespace App\Services;

use App\IService\IStudentLearningService;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Mosque;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class StudentLearningService implements IStudentLearningService
{
    public function getMosque(Student $student): ?Mosque
    {
        if (! $student->mosque_id) {
            return null;
        }

        return Mosque::query()->find($student->mosque_id);
    }

    public function getCircles(Student $student): Collection
    {
        if (! $student->mosque_id) {
            return new Collection;
        }

        return Circle::query()
            ->select('circles.*')
            ->join('student_circles', 'student_circles.circle', '=', 'circles.id')
            ->join('courses', 'courses.id', '=', 'circles.course_id')
            ->where('student_circles.student', $student->id)
            ->where('courses.mosque_id', $student->mosque_id)
            ->with(['teacher', 'course.mosque'])
            ->distinct()
            ->orderBy('circles.id')
            ->get();
    }

    public function getCourses(Student $student): Collection
    {
        if (! $student->mosque_id) {
            return new Collection;
        }

        return $this->enrolledCoursesQuery($student)
            ->with('mosque')
            ->orderBy('courses.id')
            ->get();
    }

    public function getCourseSchedule(Student $student, int $courseId): ?Course
    {
        if (! $student->mosque_id) {
            return null;
        }

        return $this->enrolledCoursesQuery($student)
            ->where('courses.id', $courseId)
            ->with([
                'mosque',
                'courseDates' => fn ($query) => $query->orderBy('session_date'),
                'courseDates.lessons' => fn ($query) => $query
                    ->whereHas(
                        'subject',
                        fn (Builder $subjectQuery) => $subjectQuery
                            ->where('subjects.course_id', $courseId)
                    )
                    ->with('subject'),
            ])
            ->first();
    }

    public function getNotes(Student $student): Collection
    {
        return $student->notes()
            ->with(['student', 'author'])
            ->latest()
            ->get();
    }

    public function getSabrs(Student $student): Collection
    {
        return $student->sabrs()
            ->with(['studentDetails', 'giverDetails', 'courseDetails'])
            ->latest()
            ->get();
    }

    public function getMemorizations(Student $student): Collection
    {
        return $student->memorizations()
            ->with(['studentDetails', 'giverDetails'])
            ->orderBy('page_number')
            ->get();
    }

    public function getReadingImprovements(Student $student): Collection
    {
        return $student->readingImprovements()
            ->with(['studentDetails', 'courseDetails'])
            ->latest()
            ->get();
    }

    public function getWarnings(Student $student): Collection
    {
        return $student->warnings()
            ->with(['studentDetails', 'warnerDetails'])
            ->latest()
            ->get();
    }

    public function getExams(Student $student): Collection
    {
        return $student->exams()
            ->with(['studentDetails', 'subjectDetails', 'courseDetails'])
            ->latest()
            ->get();
    }

    private function enrolledCoursesQuery(Student $student): Builder
    {
        return Course::query()
            ->select('courses.*')
            ->join('circles', 'circles.course_id', '=', 'courses.id')
            ->join('student_circles', 'student_circles.circle', '=', 'circles.id')
            ->where('student_circles.student', $student->id)
            ->where('courses.mosque_id', $student->mosque_id)
            ->distinct();
    }
}
