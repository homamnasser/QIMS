<?php

namespace App\Services;

use App\IService\ICourseDateService;
use App\Models\Course;
use App\Models\CourseDate;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class CourseDateService implements ICourseDateService
{
    public function generateCourseDates(int $courseId, array $data)
    {
        // جلب الكورس مرة واحدة للحصول على التواريخ الأساسية
        $course = Course::findOrFail($courseId);

        // استخدام التواريخ من قاعدة البيانات مباشرة كما في الصورة image_4678c1.png
        $startDate = Carbon::parse($course->start_date);
        $endDate = Carbon::parse($course->end_date);

        $selectedDays = $data['days'];
        $excludedDates = $data['excluded_dates'] ?? [];

        $period = CarbonPeriod::create($startDate, $endDate);
        $datesToInsert = [];

        // جلب التواريخ الموجودة مسبقاً (Optimized: استخدام Set للبحث الأسرع)
        // pluck يعيد كائنات Carbon، وقلبها يُسقط كل القيم ويترك المصفوفة فارغة،
        // فتُعاد إضافة تواريخ موجودة وتفشل على قيد التفرد؛ لذلك تُنسّق أولًا.
        $existingDates = CourseDate::where('course_id', $courseId)
            ->pluck('session_date')
            ->map(fn ($sessionDate) => $sessionDate->format('Y-m-d'))
            ->flip() // قلب المصفوفة يجعل البحث عن القيم (keys) أسرع بكثير
            ->toArray();

        foreach ($period as $date) {
            $formattedDate = $date->format('Y-m-d');
            $dayName = $date->format('l');

            if (
                in_array($dayName, $selectedDays) &&
                ! isset($existingDates[$formattedDate]) && // استخدام isset أسرع من in_array
                ! in_array($formattedDate, $excludedDates)
            ) {
                $datesToInsert[] = [
                    'course_id' => $courseId,
                    'session_date' => $formattedDate,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (! empty($datesToInsert)) {
            // استخدام chunk إذا كان عدد التواريخ ضخماً لتجنب مشاكل الذاكرة
            foreach (array_chunk($datesToInsert, 100) as $chunk) {
                CourseDate::insert($chunk);
            }

            // إرجاع التواريخ التي تم إضافتها فقط
            $newDateValues = array_column($datesToInsert, 'session_date');

            return CourseDate::where('course_id', $courseId)
                ->whereIn('session_date', $newDateValues)
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
