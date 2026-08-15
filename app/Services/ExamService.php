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

    public function getExamMarksForTeacher(int $teacherId, array $filters = []): Collection
    {
        return Exam::query()
            ->with(['studentDetails', 'subjectDetails', 'courseDetails'])
            ->filter($filters)
            ->whereExists(function ($query) use ($teacherId): void {
                $query->selectRaw('1')
                    ->from('student_circles')
                    ->join('circles', 'circles.id', '=', 'student_circles.circle')
                    ->whereColumn('student_circles.student', 'exams.student')
                    ->whereColumn('circles.course_id', 'exams.course')
                    ->where('circles.teacher_id', $teacherId);
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function getExamById(int $id): ?Exam
    {
        return Exam::find($id);
    }

    /**
     * نفس قاعدة «امتحاناتي»: السجل يخصّ المدرس إن كان طالبه في إحدى حلقاته
     * ضمن كورس الامتحان.
     */
    public function teacherOwnsExam(Exam $exam, int $teacherId): bool
    {
        return $this->getExamMarksForTeacher($teacherId, [
            'student_id' => $exam->student,
            'course_id' => $exam->course,
        ])->contains('id', $exam->id);
    }

    /**
     * تصحيح العلامة، ومعها تاريخ الاختبار إن أُرسل — فتصحيح تاريخ خاطئ يعيد
     * العلامة إلى نافذة دورتها بدل أن تبقى ساقطة من الحساب.
     */
    public function updateExamMark(Exam $exam, float $newMark, ?string $occurredAt = null): bool
    {
        return $exam->update(array_filter(
            ['mark' => $newMark, 'occurred_at' => $occurredAt],
            fn ($value) => $value !== null
        ));
    }

    public function deleteExam(Exam $exam): bool
    {
        return $exam->delete();
    }
}
