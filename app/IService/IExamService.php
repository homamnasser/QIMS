<?php

namespace App\IService;

use App\Models\Exam;
use Illuminate\Support\Collection;

interface IExamService
{
    public function createExamMark(array $data): Exam;

    public function getAllExamMarks(array $filters = []): Collection;

    public function getExamMarksForTeacher(int $teacherId, array $filters = []): Collection;

    public function getExamById(int $id): ?Exam;

    public function updateExamMark(Exam $exam, float $newMark): bool;

    public function deleteExam(Exam $exam): bool;
}
