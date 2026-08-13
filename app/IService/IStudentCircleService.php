<?php

namespace App\IService;

interface IStudentCircleService
{
    public function addStudentsToCircle(int $circleId, array $studentIds): array;

    public function removeStudentFromCircle(int $circleId, int $studentId): bool;

    public function getCircleStudents(int $circleId);
}
