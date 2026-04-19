<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\IService\ICourseService;
use App\Models\Course;
use App\Http\Resources\CourseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CourseController extends Controller
{
    protected $courseService;
    public function __construct(ICourseService $courseService)
    {
        $this->courseService = $courseService;
    }
    /* إنشاء دورة جديدة */
    public function createCourse(StoreCourseRequest $request): JsonResponse
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

    /* جلب كافة الدورات مع الفلترة */
    public function getAllCourses(Request $request): JsonResponse
    {
        $filters = $request->only([
            'name',
            'mosque_id',
            'project_id',
            'mosque_name',
            'project_name'
        ]);

        $courses = $this->courseService->getAllCourses($filters);

        return response()->json([
            'code'    => 200,
            'message' => 'Courses retrieved successfully.',
            'data'    => CourseResource::collection($courses)
        ], 200);
    }

    /* جلب دورة محددة بواسطة ID */
    public function getCourse(int $id): JsonResponse
    {
        $course = $this->courseService->getCourseById($id);

        if (!$course) {
            return response()->json([
                'code'    => 404,
                'message' => 'Course not found.',
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Course retrieved successfully.',
            'data'    => new CourseResource($course)
        ], 200);
    }

    /* تحديث دورة (مع التأكد من وجودها أولاً) */
    public function updateCourse(Request $request, int $id): JsonResponse
    {
        $course = $this->courseService->getCourseById((int)$id);

        if (!$course) {
            return response()->json([
                'code'    => 404,
                'message' => 'Course not found',
                'data'    => null
            ], 404);
        }

        if (!$course->is_active) {
            return response()->json([
                'code'    => 403,
                'message' => 'This course is not active and cannot be modified.'
            ], 403);
        }

        $courseRequest = app(UpdateCourseRequest::class);

        $validator = Validator::make(
            $request->all(),
            $courseRequest->rules(),
            $courseRequest->messages()
        );

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'message' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        $updatedCourse = $this->courseService->updateCourse($course, $validatedData);

        return response()->json([
            'code'    => 200,
            'message' => 'Course updated successfully.',
            'data'    => new CourseResource($updatedCourse)
        ], 200);
    }
    
    /* حذف دورة (مع التأكد من وجودها أولاً) */
    public function deleteCourse(int $id): JsonResponse
    {
        $course = $this->courseService->getCourseById((int)$id);

        if (!$course) {
            return response()->json([
                'code'    => 404,
                'message' => 'Course not found',
            ], 404);
        }

        $isDeleted = $this->courseService->deleteCourse($course);

        if ($isDeleted) {
            return response()->json([
                'code'    => 200,
                'message' => 'Course deleted successfully.',
            ], 200);
        }
        return response()->json([
            'code'    => 500,
            'message' => 'Something went wrong while deleting the course.',
        ], 500);
    }
    /* تفعيل أو أرشفة دورة (مع التأكد من وجودها أولاً) */
    public function editCourseStatus(int $id): JsonResponse
    {
        $course = $this->courseService->getCourseById((int)$id);

        if (!$course) {
            return response()->json([
                'code'    => 404,
                'message' => 'Course not found'
            ], 404);
        }

        $updatedCourse = $this->courseService->editCourseStatus($course);

        return response()->json([
            'code'    => 200,
            'message' => $updatedCourse->is_active ? 'Course activated successfully' : 'Course archived successfully',
            'data'    => new CourseResource($updatedCourse)
        ], 200);
    }
}
