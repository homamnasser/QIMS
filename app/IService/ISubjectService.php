<?php
namespace App\IService;

use App\Models\Subject;

interface ISubjectService
{
    public function getAllSubjects(array $filters);
    public function getSubjectById(int $id): ?Subject;
    public function createSubject(array $data): Subject;
    public function updateSubject(Subject $subject, array $data): Subject;
    public function deleteSubject(Subject $subject): bool;
}
