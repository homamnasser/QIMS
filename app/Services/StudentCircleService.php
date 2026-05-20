<?php

namespace App\Services;

use App\IService\IStudentCircleService;
use App\Models\StudentCircle;
use App\Models\Student;

class StudentCircleService implements IStudentCircleService
{
    public function addStudentsToCircle(int $circleId, array $studentIds): array
    {
        $uniqueStudentIds = array_unique($studentIds);

        $newlyAddedStudentIds = [];
        $skippedCount = 0;

        foreach ($uniqueStudentIds as $studentId) {
            $exists = StudentCircle::where('student', $studentId)
                ->where('circle', $circleId)
                ->exists();

            if (!$exists) {
                StudentCircle::create([
                    'student' => $studentId,
                    'circle'  => $circleId
                ]);
                $newlyAddedStudentIds[] = $studentId;
            } else {
                $skippedCount++;
            }
        }

        $addedStudents = Student::whereIn('id', $newlyAddedStudentIds)->get();

        return [
            'students'      => $addedStudents,
            'skipped_count' => $skippedCount
        ];
    }

    public function removeStudentFromCircle(int $circleId, int $studentId): bool
    {
        return (bool) StudentCircle::where('circle', $circleId)
            ->where('student', $studentId)
            ->delete();
    }

    public function getCircleStudents(int $circleId)
    {
        return StudentCircle::where('circle', $circleId)
            ->with('studentDetails')
            ->get()
            ->pluck('studentDetails')
            ->filter();
    }
}
