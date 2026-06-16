<?php

namespace App\Services;
use App\IService\IExamService;
use App\Models\Exam;
use Illuminate\Support\Collection;
class ExamService implements IExamService
{

    public function createExamMark(array $data): Exam
    {
        return Exam::create($data);
    }


    public function getAllExamMarks(array $filters = []): Collection
    {
        return Exam::query()
            ->with(['studentDetails', 'subjectDetails', 'courseDetails'])
            ->filter($filters)
            ->orderBy('created_at', 'desc')
            ->get();
    }
    public function getExamById(int $id): ?Exam
    {
        return Exam::find($id);
    }
    public function updateExamMark(Exam $exam, float $newMark): bool
    {
        return $exam->update(['mark' => $newMark]);
    }

    
    public function deleteExam(Exam $exam): bool
    {
        return $exam->delete();
    }
}
