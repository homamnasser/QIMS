<?php

namespace App\Services;

use App\Models\CourseDate;
use App\IService\ICourseDateService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;



class CourseDateService implements ICourseDateService
{
    public function generateCourseDates(int $courseId, array $data)
    {
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $selectedDays = $data['days'];

        // جلب التواريخ المستثناة من الطلب (إذا لم توجد نضع مصفوفة فارغة)
        $excludedDates = $data['excluded_dates'] ?? [];

        $period = CarbonPeriod::create($startDate, $endDate);
        $datesToInsert = [];

        // جلب التواريخ الموجودة مسبقاً لهذا الكورس لتجنب تكرارها
        $existingDates = CourseDate::where('course_id', $courseId)
            ->pluck('session_date')
            ->toArray();

        foreach ($period as $date) {
            $formattedDate = $date->format('Y-m-d');

            // التحقق من 3 شروط:
            // 1. هل اليوم من الأيام المختارة (مثلاً السبت)؟
            // 2. هل التاريخ غير موجود مسبقاً في قاعدة البيانات؟
            // 3. هل التاريخ غير موجود في قائمة الاستثناءات (العطلات) المرسلة؟
            if (
                in_array($date->format('l'), $selectedDays) &&
                !in_array($formattedDate, $existingDates) &&
                !in_array($formattedDate, $excludedDates)
            ) {

                $datesToInsert[] = [
                    'course_id'    => $courseId,
                    'session_date' => $formattedDate,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }
        }

        if (!empty($datesToInsert)) {
            CourseDate::insert($datesToInsert);

            $newDates = array_column($datesToInsert, 'session_date');
            return CourseDate::where('course_id', $courseId)
                ->whereIn('session_date', $newDates)
                ->get();
        }

        return null;
    }

    public function getDatesByCourse(int $courseId)
    {
        return CourseDate::where('course_id', $courseId)->orderBy('session_date')->get();
    }

    public function deleteDate(CourseDate $courseDate)
    {
        return $courseDate->delete();
    }
    public function getCourseDateById(int $id)
    {
        return CourseDate::find($id);
    }
    public function addManualDate(array $data)
    {
        return CourseDate::create($data);
    }
   
}
