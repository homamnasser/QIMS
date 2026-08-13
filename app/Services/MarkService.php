<?php

namespace App\Services;

use App\IService\IMarkService;
use App\Models\StudentMark;
use Illuminate\Support\Facades\DB;

class MarkService implements IMarkService
{
    /**
     * 🧮 حساب إحصائيات وعلامة حضور الطالب ديناميكياً خلال الدورة
     */
    public function calculateStudentAttendanceMetrics(int $studentId, int $courseId): array
    {
        // 1. جلب إجمالي أيام الدوام الفعلية المخصصة للدورة من جدول course_dates
        $totalSessions = DB::table('course_dates')
            ->where('course_id', $courseId)
            ->count();

        // حماية هندسية: إذا لم يتم تحديد أيام دوام للدورة بعد، نرجع أصفار لتجنب القسمة على صفر
        if ($totalSessions === 0) {
            return [
                'total_sessions' => 0,
                'present_days' => 0,
                'late_first_period' => 0,
                'late_second_period' => 0,
                'absent_days' => 0,
                'attendance_percentage' => 0,
                'attendance_marks' => 0,
            ];
        }

        // 2. استرجاع جميع سجلات الحضور والغياب والتأخر للطالب خلال هذه الدورة
        $attendanceRecords = DB::table('student_course_absences')
            ->where('student', $studentId)
            ->where('course', $courseId)
            ->get();

        // فرز وعد الأيام المسجلة في قاعدة البيانات لكل حالة 📊
        $presentDaysRaw = $attendanceRecords->where('type', 'present')->count();
        $fullAbsences = $attendanceRecords->where('type', 'full')->count();
        $lateFirst = $attendanceRecords->where('type', 'first_period')->count();
        $lateSecond = $attendanceRecords->where('type', 'second_period')->count();

        // 3. احتساب التأخرات وتحويلها إلى غيابات وفق القاعدة المحددة:
        // ⚠️ 6 تأخرات فترة أولى = غياب يوم كامل
        $absencesFromFirst = floor($lateFirst / 6);

        // ⚠️ 4 تأخرات فترة ثانية = غياب يوم كامل
        $absencesFromSecond = floor($lateSecond / 4);

        // إجمالي أيام الغياب (الغياب الفعلي الكامل + الغياب الناتج عن عقوبات التأخر)
        $totalEquivalentAbsences = $fullAbsences + $absencesFromFirst + $absencesFromSecond;

        // حساب أيام الحضور الصافية المعتمدة للطالب
        $calculatedPresentDays = $totalSessions - $totalEquivalentAbsences;
        if ($calculatedPresentDays < 0) {
            $calculatedPresentDays = 0; // حماية لكي لا تصبح الأيام بالسالب
        }

        // 4. حساب نسبة الحضور المئوية 📈
        $attendancePercentage = ($calculatedPresentDays / $totalSessions) * 100;

        // 5. حساب علامة الحضور الأساسية (الحضور عليه 100 علامة)
        $baseMarks = $attendancePercentage;

        // 6. حساب نظام التحفيز والبونص 🎁
        // إذا حقق الطالب 90% حضور أو أكثر، يضاف 3 درجات لكل 1% فوق الـ 90%
        $bonusMarks = 0;
        if ($attendancePercentage >= 90) {
            $percentageAboveNinety = floor($attendancePercentage - 90);
            $bonusMarks = $percentageAboveNinety * 3;
        }

        // العلامة النهائية = العلامة الأساسية + البونص
        $finalAttendanceMarks = $baseMarks + $bonusMarks;

        // 🛑 قفل العلامة بحد أقصى عند 100 علامة كما هو محدد للحضور
        if ($finalAttendanceMarks > 100) {
            $finalAttendanceMarks = 100;
        }

        // 7. إرجاع مصفوفة النتائج الكاملة للـ Flow
        return [
            'total_sessions' => $totalSessions,
            'present_days_raw' => $presentDaysRaw,
            'late_first_period' => $lateFirst,
            'late_second_period' => $lateSecond,
            'actual_absent_days' => $fullAbsences,
            'converted_absences' => (int) ($absencesFromFirst + $absencesFromSecond),
            'total_absences_penalty' => (int) $totalEquivalentAbsences,
            'calculated_present' => $calculatedPresentDays,
            'attendance_percentage' => round($attendancePercentage, 2),
            'attendance_marks' => round($finalAttendanceMarks, 2),
        ];
    }

    /**
     * 🚀 إنشاء سجل علامات جديد للطالب
     */
    public function createStudentMark(array $data): StudentMark
    {
        return StudentMark::create($data);
    }
}
