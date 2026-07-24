<?php

namespace App\Services;

use App\IService\ICircleService;
use App\Models\Circle;
use App\Models\CourseDate;
use App\Models\StudentCircle;
use Illuminate\Support\Facades\DB;

class CircleService implements ICircleService
{
    public function __construct(private readonly StudentSelfNumberService $selfNumberService) {}

    public function createCircle(array $data): Circle
    {
        return Circle::create($data);
    }

    public function getCircleCurriculumByTeacher(int $teacherId)
    {
        $circle = Circle::where('teacher_id', $teacherId)->first();

        if (! $circle) {
            return null;
        }

        return CourseDate::with(['lessons'])
            ->where('course_id', $circle->course_id)
            ->orderBy('session_date', 'asc')
            ->get();
    }

    public function getCircleById(int $id): ?Circle
    {
        return Circle::find($id);
    }

    public function getAllCircles(array $filters)
    {
        $query = Circle::with(['teacher', 'course.mosque'])
            ->filter($filters);

        return isset($filters['limit']) ? $query->paginate($filters['limit']) : $query->get();
    }

    public function deleteCircle(Circle $circle): bool
    {
        return DB::transaction(function () use ($circle): bool {
            $studentIds = StudentCircle::query()
                ->where('circle', $circle->id)
                ->pluck('student')
                ->all();

            $deleted = $circle->delete();

            if ($deleted) {
                foreach ($studentIds as $studentId) {
                    $this->selfNumberService->deactivateIfUnenrolled((int) $studentId);
                }
            }

            return $deleted;
        }, 3);
    }

    public function updateCircle(Circle $circle, array $data): Circle
    {
        $circle->update($data);

        return $circle->fresh();
    }
}
