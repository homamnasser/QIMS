<?php

namespace App\Services;

use App\IService\ISubjectService;
use App\Models\Subject;

class SubjectService implements ISubjectService
{
    public function getAllSubjects(array $filters)
    {
        return Subject::with(['course', 'sharedSubject'])
            ->filter($filters)
            ->latest()
            ->get();
    }

    public function getSubjectById(int $id): ?Subject
    {
        return Subject::find($id);
    }

    public function createSubject(array $data): Subject
    {
        return Subject::create($data);
    }

    public function updateSubject(Subject $subject, array $data): Subject
    {
        $subject->update($data);
        return $subject->fresh();
    }

    public function deleteSubject(Subject $subject): bool
    {
        return $subject->delete();
    }
    
}
