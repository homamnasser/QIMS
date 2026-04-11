<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreCourseRequest;
use App\IService\ICourseService;
use App\Models\Course;
use App\Http\Resources\CourseResource;

class CourseController extends Controller
{
    protected $courseService;
    public function __construct(ICourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    public function createCourse(StoreCourseRequest $request)
    {
        if ($request->filled('parent_course_id')) {
            $exists = Course::where('parent_course_id', $request->parent_course_id)->exists();

            if ($exists) {
                return response()->json([
                    'code'    => 400,
                    'message' => 'The selected parent course already has a sub-course assigned to it.'
                ], 400);
            }
        }
        $course = $this->courseService->createCourse($request->validated());
        return response()->json([
            'code'    => 201,
            'message' => 'Course created successfully.',
            'data'    => new CourseResource($course->fresh())
        ], 201);
    }
}
