<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ReportApiController extends Controller
{
    /* Endpoint 1: Courses and Students */
    public function getCoursesStudents(\Illuminate\Http\Request $request): JsonResponse
    {
        $limit = $request->query('limit', 9);
        $courses = \App\Models\Course::paginate($limit);
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
            'data' => $data,
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ]
        ], 200);
    }

    /* Endpoint 2: User and Student Name */
    public function getStudentInfo(\Illuminate\Http\Request $request): JsonResponse
    {
        $limit = $request->query('limit', 9);
        $students = \App\Models\Student::paginate($limit);
        $data = [];

        foreach ($students as $student) {
            $data[] = [
                'user_id' => $student->id,
                'student_name' => trim($student->first_name . ' ' . $student->last_name)
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
            ]
        ], 200);
    }
}
