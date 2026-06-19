<?php

namespace App\Services;

use App\IService\IStudentCourseAbsenceService;
use App\Models\StudentCourseAbsence;
use Illuminate\Support\Collection;

class StudentCourseAbsenceService implements IStudentCourseAbsenceService
{
    public function createAbsence(array $data): StudentCourseAbsence
    {
        return StudentCourseAbsence::create($data);
    }

    public function getAllAbsences(array $filters = []): Collection
    {
        return StudentCourseAbsence::query()
            ->with(['studentDetails', 'courseDetails'])
            ->filter($filters)
            ->orderBy('date', 'desc')
            ->get();
    }

    public function getAbsenceById(int $id): ?StudentCourseAbsence
    {
        return StudentCourseAbsence::find($id);
    }

    public function updateAbsence(StudentCourseAbsence $absence, array $data): bool
    {
        return $absence->update($data);
    }

    public function deleteAbsence(StudentCourseAbsence $absence): bool
    {
        return $absence->delete();
    }
}
