<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\Student;
use App\Models\StudentCircle;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportApiController extends Controller
{
    /* Endpoint 1: Courses and Students */
    public function getCoursesStudents(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 9);
        $courses = Course::paginate($limit);
        $data = [];

        foreach ($courses as $course) {
            $circleIds = Circle::where('course_id', $course->id)->pluck('id');
            $studentIds = StudentCircle::whereIn('circle', $circleIds)
                ->pluck('student')
                ->unique()
                ->values()
                ->toArray();

            // pluck يطبّق الـ cast فيعيد كائنات Carbon، وترميزها إلى JSON ينتج
            // طابعًا زمنيًا كاملًا؛ لذلك تُنسّق صراحةً كبقية التواريخ في هذا الملف.
            $courseDays = CourseDate::where('course_id', $course->id)
                ->orderBy('session_date')
                ->pluck('session_date')
                ->map(fn ($sessionDate) => $sessionDate->format('Y-m-d'))
                ->all();

            $data[] = [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'course_date' => [
                    'start_date' => $course->start_date ? ($course->start_date instanceof Carbon ? $course->start_date->format('Y-m-d') : (string) $course->start_date) : null,
                    'end_date' => $course->end_date ? ($course->end_date instanceof Carbon ? $course->end_date->format('Y-m-d') : (string) $course->end_date) : null,
                    'course_days' => $courseDays,
                ],
                'student_in_course' => $studentIds,
            ];
        }

        return response()->json([
            'status' => 200,
            'message' => 'تم بنجاح',
            'data' => $data,
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ],
        ], 200);
    }

    /* Endpoint 2: User and Student Name */
    public function getStudentInfo(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 9);
        $students = Student::paginate($limit);
        $data = [];

        foreach ($students as $student) {
            $data[] = [
                'user_id' => $student->id,
                'student_name' => trim($student->first_name.' '.$student->last_name),
            ];
        }

        return response()->json([
            'status' => 200,
            'data' => $data,
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ], 200);
    }

    /* Endpoint 3: Courses Schedule (Dates and Lessons) Report */
    public function getCoursesDatesLessons(Request $request): JsonResponse
    {
        $courses = Course::all();
        $data = [];

        $daysMap = [
            'Sunday' => 'الأحد',
            'Monday' => 'الإثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
            'Saturday' => 'السبت',
        ];

        foreach ($courses as $course) {
            $courseDates = CourseDate::with('lessons')
                ->where('course_id', $course->id)
                ->orderBy('session_date', 'asc')
                ->get();

            $formattedDays = [];
            $assignedLessons = [];

            foreach ($courseDates as $cDate) {
                $dateStr = $cDate->session_date ? ($cDate->session_date instanceof Carbon ? $cDate->session_date->format('Y-m-d') : (string) $cDate->session_date) : null;
                if (! $dateStr) {
                    continue;
                }

                try {
                    $carbonDate = Carbon::parse($dateStr);
                    $dayEng = $carbonDate->format('l');
                    $dayAr = $daysMap[$dayEng] ?? $dayEng;
                } catch (\Exception $e) {
                    $dayAr = '';
                }

                $fullDate = $dayAr ? ($dayAr.' '.$dateStr) : $dateStr;

                $formattedDays[] = [
                    'date' => $dateStr,
                    'day_name' => $dayAr,
                    'full_date' => $fullDate,
                ];

                $lessonNames = $cDate->lessons->pluck('name')->toArray();

                $assignedLessons[] = [
                    'date' => $dateStr,
                    'day_name' => $dayAr,
                    'full_date' => $fullDate,
                    'lessons' => $lessonNames,
                    'lessons_str' => count($lessonNames) > 0 ? implode('، ', $lessonNames) : 'لا يوجد دروس',
                ];
            }

            $startDateStr = $course->start_date ? ($course->start_date instanceof Carbon ? $course->start_date->format('Y-m-d') : (string) $course->start_date) : null;
            $endDateStr = $course->end_date ? ($course->end_date instanceof Carbon ? $course->end_date->format('Y-m-d') : (string) $course->end_date) : null;

            $data[] = [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'start_date' => $startDateStr,
                'end_date' => $endDateStr,
                'course_days' => $formattedDays,
                'assigned_lessons' => $assignedLessons,
                'is_active' => (bool) $course->is_active,
            ];
        }

        return response()->json([
            'status' => 200,
            'message' => 'تم جلب تواريخ الكورس والدروس بنجاح.',
            'data' => $data,
        ], 200);
    }
}
