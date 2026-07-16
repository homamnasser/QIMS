<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ReportApiController extends Controller
{
    /* Endpoint 1: Courses and Students */
    public function getCoursesStudents(): JsonResponse
    {
        $courses = \App\Models\Course::all();
        $data = [];

        foreach ($courses as $course) {
            $circleIds = \App\Models\Circle::where('course_id', $course->id)->pluck('id');
            $studentIds = \App\Models\StudentCircle::whereIn('circle', $circleIds)
                ->pluck('student')
                ->unique()
                ->values()
                ->toArray();
            
            $data[] = [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'student_in_course' => $studentIds
            ];
        }

        return response()->json([
            'status' => 200,
            'message' => 'ok',
            'data' => $data
        ], 200);
    }

    /* Endpoint 2: User and Student Name */
    public function getStudentInfo(): JsonResponse
    {
        $students = \App\Models\Student::all();
        $data = [];

        foreach ($students as $student) {
            $data[] = [
                'user_id' => $student->id,
                'student_name' => trim($student->first_name . ' ' . $student->last_name)
            ];
        }

        return response()->json([
            'status' => 200,
            'data' => $data
        ], 200);
    }
}
