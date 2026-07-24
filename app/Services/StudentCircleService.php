<?php

namespace App\Services;

use App\IService\IStudentCircleService;
use App\Models\Circle;
use App\Models\Student;
use App\Models\StudentCircle;
use Illuminate\Support\Facades\DB;

class StudentCircleService implements IStudentCircleService
{
    public function __construct(private readonly StudentSelfNumberService $selfNumberService) {}

    public function addStudentsToCircle(int $circleId, array $studentIds): array
    {
        $uniqueStudentIds = array_unique($studentIds);

        // جلب تفاصيل الحلقة المستهدفة مع الكورس والمسجد
        $targetCircle = Circle::with('course.mosque')->find($circleId);
        $targetCourseId = $targetCircle?->course_id;
        $targetMosqueId = $targetCircle?->course?->mosque_id;

        $newlyAddedStudentIds = [];
        $skippedCount = 0;
        $conflicts = [];

        foreach ($uniqueStudentIds as $studentId) {
            $result = DB::transaction(function () use (
                $studentId,
                $circleId,
                $targetCircle,
                $targetCourseId,
                $targetMosqueId,
            ): array {
                $student = Student::query()->lockForUpdate()->find($studentId);
                if (! $student) {
                    return ['added' => false, 'conflict' => null];
                }

                // 1. الفحص الأولي: هل الطالب مضاف مسبقاً لهذه الحلقة نفسها؟
                $existsInCircle = StudentCircle::where('student', $studentId)
                    ->where('circle', $circleId)
                    ->exists();

                if ($existsInCircle) {
                    return ['added' => false, 'conflict' => null];
                }

                // 2. لا يجوز أن ينتمي الطالب إلى حلقتين مختلفتين ضمن الكورس نفسه.
                if ($targetCourseId) {
                    $sameCourseAssignment = StudentCircle::where('student', $studentId)
                        ->where('circle', '!=', $circleId)
                        ->whereHas('circleDetails', function ($q) use ($targetCourseId) {
                            $q->where('course_id', $targetCourseId);
                        })
                        ->with('circleDetails')
                        ->first();

                    if ($sameCourseAssignment) {
                        $studentName = trim($student->first_name.' '.$student->last_name);
                        $otherCircleName = $sameCourseAssignment->circleDetails?->name ?? 'حلقة أخرى';
                        $courseName = $targetCircle?->course?->name ?? 'الكورس نفسه';

                        return [
                            'added' => false,
                            'conflict' => "الطالب '{$studentName}' مسجّل مسبقاً في حلقة '{$otherCircleName}' ضمن الكورس '{$courseName}'. لا يمكن تسجيل الطالب في أكثر من حلقة للكورس نفسه، ويمكن تسجيله في كورسات مختلفة ضمن المسجد نفسه فقط.",
                        ];
                    }
                }

                // 3. هل ينتمي الطالب مسبقاً لأي حلقة في مسجد آخر مختلف؟
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
                        $studentName = trim($student->first_name.' '.$student->last_name);

                        return [
                            'added' => false,
                            'conflict' => "الطالب '{$studentName}' ينتمي مسبقاً إلى حلقة في ({$otherMosqueName})، ولا يمكن تسجيله في مساجد مختلفة.",
                        ];
                    }
                }

                StudentCircle::firstOrCreate([
                    'student' => $studentId,
                    'circle' => $circleId,
                ]);

                if ($targetCircle?->course?->mosque) {
                    $this->selfNumberService->ensureForMosque($student, $targetCircle->course->mosque);
                }

                return ['added' => true, 'conflict' => null];
            }, 3);

            if ($result['added']) {
                $newlyAddedStudentIds[] = $studentId;
            } else {
                $skippedCount++;
                if ($result['conflict']) {
                    $conflicts[] = $result['conflict'];
                }
            }
        }

        $addedStudents = Student::whereIn('id', $newlyAddedStudentIds)->get();

        return [
            'students' => $addedStudents,
            'skipped_count' => $skippedCount,
            'conflicts' => $conflicts,
        ];
    }

    public function removeStudentFromCircle(int $circleId, int $studentId): bool
    {
        return DB::transaction(function () use ($circleId, $studentId): bool {
            $student = Student::query()->lockForUpdate()->find($studentId);
            if (! $student) {
                return false;
            }

            $assignment = StudentCircle::query()
                ->where('circle', $circleId)
                ->where('student', $studentId)
                ->lockForUpdate()
                ->first();

            if (! $assignment) {
                return false;
            }

            $assignment->delete();
            $this->selfNumberService->deactivateIfUnenrolled($student->id);

            return true;
        }, 3);
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
