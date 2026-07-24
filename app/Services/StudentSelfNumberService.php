<?php

namespace App\Services;

use App\Models\Mosque;
use App\Models\Student;
use App\Models\StudentCircle;
use App\Models\StudentSelfNumberReservation;
use Illuminate\Support\Facades\DB;
use LogicException;

class StudentSelfNumberService
{
    public function ensureForMosque(Student $student, Mosque $mosque): Student
    {
        return DB::transaction(function () use ($student, $mosque): Student {
            $lockedStudent = Student::query()->lockForUpdate()->findOrFail($student->id);

            $isEnrolledInMosque = StudentCircle::query()
                ->where('student', $lockedStudent->id)
                ->whereHas('circleDetails.course', function ($query) use ($mosque): void {
                    $query->where('mosque_id', $mosque->id);
                })
                ->exists();

            if (! $isEnrolledInMosque) {
                throw new LogicException('لا يمكن إنشاء رقم ذاتي قبل إلحاق الطالب بحلقة دراسية في المسجد.');
            }

            if ($lockedStudent->selfnumber) {
                if ((int) $lockedStudent->mosque_id !== (int) $mosque->id) {
                    throw new LogicException('لا يمكن إنشاء رقم ذاتي جديد لطالب مرتبط بمسجد آخر.');
                }

                $reservation = StudentSelfNumberReservation::query()->firstOrCreate(
                    ['selfnumber' => $lockedStudent->selfnumber],
                    [
                        'student_id' => $lockedStudent->id,
                        'mosque_id' => $mosque->id,
                        'assigned_at' => $lockedStudent->created_at ?? now(),
                    ],
                );

                if (
                    (int) $reservation->student_id !== (int) $lockedStudent->id
                    || (int) $reservation->mosque_id !== (int) $mosque->id
                    || $reservation->deactivated_at
                ) {
                    throw new LogicException('الرقم الذاتي الحالي محجوز تاريخيًا ولا يمكن إعادة تفعيله أو إسناده من جديد.');
                }

                return $lockedStudent;
            }

            $lockedMosque = Mosque::query()->lockForUpdate()->findOrFail($mosque->id);
            if (! $lockedMosque->mosque_code) {
                throw new LogicException('يجب تعيين رمز المسجد قبل إلحاق الطلاب به.');
            }

            $sequenceLength = max(1, (int) config('surveys.selfnumber_sequence_length', 6));
            $nextSequence = (int) $lockedMosque->next_student_sequence;

            do {
                $nextSequence++;
                $selfnumber = $lockedMosque->mosque_code.'-'.str_pad(
                    (string) $nextSequence,
                    $sequenceLength,
                    '0',
                    STR_PAD_LEFT,
                );
            } while (
                StudentSelfNumberReservation::query()->where('selfnumber', $selfnumber)->exists()
                || Student::query()->where('selfnumber', $selfnumber)->exists()
            );

            $lockedMosque->forceFill(['next_student_sequence' => $nextSequence])->save();
            StudentSelfNumberReservation::query()->create([
                'selfnumber' => $selfnumber,
                'student_id' => $lockedStudent->id,
                'mosque_id' => $lockedMosque->id,
                'assigned_at' => now(),
            ]);
            $lockedStudent->forceFill([
                'mosque_id' => $lockedMosque->id,
                'selfnumber' => $selfnumber,
            ])->save();

            return $lockedStudent->refresh();
        }, 3);
    }

    public function deactivateIfUnenrolled(int $studentId): bool
    {
        return DB::transaction(function () use ($studentId): bool {
            $lockedStudent = Student::query()->lockForUpdate()->find($studentId);

            if (! $lockedStudent || ! $lockedStudent->selfnumber) {
                return false;
            }

            if (StudentCircle::query()->where('student', $lockedStudent->id)->exists()) {
                return false;
            }

            $reservation = StudentSelfNumberReservation::query()
                ->where('selfnumber', $lockedStudent->selfnumber)
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                $reservation = StudentSelfNumberReservation::query()->create([
                    'selfnumber' => $lockedStudent->selfnumber,
                    'student_id' => $lockedStudent->id,
                    'mosque_id' => $lockedStudent->mosque_id,
                    'assigned_at' => $lockedStudent->created_at ?? now(),
                ]);
            }

            if (! $reservation->deactivated_at) {
                $reservation->forceFill(['deactivated_at' => now()])->save();
            }

            $lockedStudent->forceFill([
                'mosque_id' => null,
                'selfnumber' => null,
            ])->save();

            return true;
        }, 3);
    }
}
