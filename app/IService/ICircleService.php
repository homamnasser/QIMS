<?php

namespace App\IService;

use App\Models\Circle;
use Illuminate\Support\Collection;

interface ICircleService
{
    public function createCircle(array $data): Circle;
    public function getCircleCurriculumByTeacher(int $teacherId);
    public function getCircleById(int $id): ?Circle;
    public function getAllCircles(array $filters): Collection;
    public function deleteCircle(Circle $circle): bool;
}
