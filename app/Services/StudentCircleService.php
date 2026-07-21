<?php

namespace App\Services;

use App\IService\IStudentCircleService;
use App\Models\StudentCircle;
use App\Models\Student;

class StudentCircleService implements IStudentCircleService
{
    public function addStudentsToCircle(int $circleId, array $studentIds): array
    {
        $uniqueStudentIds = array_unique($studentIds);

        // جلب تفاصيل الحلقة المستهدفة مع الكورس والمسجد
        $targetCircle = \App\Models\Circle::with('course.mosque')->find($circleId);
        $targetMosqueId = $targetCircle?->course?->mosque_id;

        $newlyAddedStudentIds = [];
        $skippedCount = 0;
        $conflicts = [];

        foreach ($uniqueStudentIds as $studentId) {
            $student = Student::find($studentId);
            if (!$student) {
                $skippedCount++;
                continue;
            }

            // 1. الفحص الأولي: هل الطالب مضاف مسبقاً لهذه الحلقة نفسها؟
            $existsInCircle = StudentCircle::where('student', $studentId)
                ->where('circle', $circleId)
                ->exists();

            if ($existsInCircle) {
                $skippedCount++;
                continue;
            }

            // 2. الفحص الهندسي: هل ينتمي الطالب مسبقاً لأي حلقة في مسجد آخر مختلف؟
            if ($targetMosqueId) {
                $existingAssignment = StudentCircle::where('student', $studentId)
                    ->whereHas('circleDetails.course', function ($q) use ($targetMosqueId) {
                        $q->whereNotNull('mosque_id')
                          ->where('mosque_id', '!=', $targetMosqueId);
                    })
                    ->with('circleDetails.course.mosque')
                    ->first();

                if ($existingAssignment) {
                    $otherMosqueName = $existingAssignment->circleDetails?->course?->mosque?->name ?? 'مسجد آخر';
                    $studentName = trim($student->first_name . ' ' . $student->last_name);
                    $conflicts[] = "الطالب '{$studentName}' ينتمي مسبقاً إلى حلقة في ({$otherMosqueName})، ولا يمكن تسجيله في مساجد مختلفة.";
                    $skippedCount++;
                    continue;
                }
            }

            // إنشاء السجل في حال نجاح الفحص
            StudentCircle::create([
                'student' => $studentId,
                'circle'  => $circleId
            ]);
            $newlyAddedStudentIds[] = $studentId;
        }

        $addedStudents = Student::whereIn('id', $newlyAddedStudentIds)->get();

        return [
            'students'      => $addedStudents,
            'skipped_count' => $skippedCount,
            'conflicts'     => $conflicts,
        ];
    }

    public function removeStudentFromCircle(int $circleId, int $studentId): bool
    {
        return (bool) StudentCircle::where('circle', $circleId)
            ->where('student', $studentId)
            ->delete();
    }

    public function getCircleStudents(int $circleId)
    {
        return StudentCircle::where('circle', $circleId)
            ->with('studentDetails')
            ->get()
            ->pluck('studentDetails')
            ->filter();
    }
}
