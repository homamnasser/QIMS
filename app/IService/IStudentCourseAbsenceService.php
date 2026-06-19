<?php

namespace App\IService;

use App\Models\StudentCourseAbsence;
use Illuminate\Support\Collection;

interface IStudentCourseAbsenceService
{
    public function createAbsence(array $data): StudentCourseAbsence;

    public function getAllAbsences(array $filters = []): Collection;

    public function getAbsenceById(int $id): ?StudentCourseAbsence;

    public function updateAbsence(StudentCourseAbsence $absence, array $data): bool;

    public function deleteAbsence(StudentCourseAbsence $absence): bool;
}
