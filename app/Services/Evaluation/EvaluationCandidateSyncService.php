<?php

namespace App\Services\Evaluation;

use App\Models\EvaluationCandidate;
use App\Models\EvaluationCandidateEnrollment;
use App\Models\EvaluationCycle;
use App\Models\StudentCircle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluationCandidateSyncService
{
    public function sync(EvaluationCycle $cycle): array
    {
        if (! in_array($cycle->status, ['draft', 'data_collection'], true)) {
            throw ValidationException::withMessages([
                'cycle' => ['لا يمكن مزامنة المرشحين بعد إغلاق مرحلة جمع البيانات.'],
            ]);
        }

        $courseIds = $cycle->courses()->pluck('courses.id');
        if ($courseIds->isEmpty()) {
            throw ValidationException::withMessages([
                'courses' => ['يجب ربط مقرر واحد على الأقل بدورة التقييم.'],
            ]);
        }

        $registrations = StudentCircle::query()
            ->with([
                'studentDetails.mosque',
                'circleDetails.course',
                'circleDetails.teacher',
            ])
            ->whereHas('circleDetails', fn ($query) => $query->whereIn('course_id', $courseIds))
            ->get();

        return DB::transaction(function () use ($cycle, $registrations) {
            $candidateCount = 0;
            $enrollmentCount = 0;

            foreach ($registrations as $registration) {
                $student = $registration->studentDetails;
                $circle = $registration->circleDetails;
                if (! $student || ! $circle || ! $circle->course) {
                    continue;
                }

                $candidate = EvaluationCandidate::updateOrCreate(
                    [
                        'evaluation_cycle_id' => $cycle->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'mosque_id' => $student->mosque_id,
                        'academic_class_snapshot' => $student->academic_class,
                        'reading_level_snapshot' => $student->reading_level,
                        'status' => 'active',
                        'status_reason' => null,
                    ]
                );
                $candidateCount++;

                EvaluationCandidateEnrollment::updateOrCreate(
                    [
                        'evaluation_candidate_id' => $candidate->id,
                        'course_id' => $circle->course_id,
                        'circle_id' => $circle->id,
                    ],
                    [
                        'teacher_id' => $circle->teacher_id,
                        'course_name_snapshot' => $circle->course->name,
                        'circle_name_snapshot' => $circle->name,
                        'quran_mode_snapshot' => $circle->quran_mode->value,
                        'teacher_name_snapshot' => $circle->teacher
                            ? trim($circle->teacher->first_name.' '.$circle->teacher->last_name)
                            : null,
                    ]
                );
                $enrollmentCount++;
            }

            return [
                'candidates' => $candidateCount,
                'enrollments' => $enrollmentCount,
            ];
        });
    }
}
