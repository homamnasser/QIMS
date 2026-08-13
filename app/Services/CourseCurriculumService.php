<?php

namespace App\Services;

use App\IService\ICourseCurriculumService;
use App\Models\CourseDate;
use Illuminate\Support\Facades\Log;

class CourseCurriculumService implements ICourseCurriculumService
{
    public function assignLessonsToDate(CourseDate $courseDate, array $lessonIds)
    {
        $courseDate->lessons()->syncWithoutDetaching($lessonIds);

        return $courseDate->load('lessons');
    }

    public function updateCourseCurriculum(CourseDate $courseDate, array $lessonIds)
    {

        $courseDate->lessons()->sync($lessonIds);

        return $courseDate->load('lessons');
    }

    public function detachAllLessons(int $courseDateId): bool
    {
        try {
            $courseDate = CourseDate::find($courseDateId);
            if (! $courseDate) {
                return false;
            }

            $courseDate->lessons()->detach();

            return true;
        } catch (\Exception $e) {
            Log::error('Error in detachAllLessons: '.$e->getMessage());

            return false;
        }
    }

    public function getCourseDateWithLessons(int $id)
    {
        // هنا نحدد العلاقات التي نريد جلبها دائماً مع التقويم
        return CourseDate::with(['lessons', 'course'])->find($id);
    }

    public function getFullCourseCurriculum(int $courseId)
    {
        return CourseDate::with(['lessons'])
            ->where('course_id', $courseId)
            ->orderBy('session_date', 'asc')
            ->get();
    }
}
