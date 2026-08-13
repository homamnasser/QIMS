<?php

namespace App\IService;

use App\Models\Circle;

interface ICircleService
{
    public function createCircle(array $data): Circle;

    public function getCircleCurriculumByTeacher(int $teacherId);

    public function getCircleById(int $id): ?Circle;

    public function getAllCircles(array $filters);

    public function deleteCircle(Circle $circle): bool;

    public function updateCircle(Circle $circle, array $data): Circle;
}
