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
use Illuminate\Support\Facades\Auth;

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
                    'message' => 'الكورس الأب المحدد يحتوي مسبقاً على كورس فرعي مرتبط به.'
                ], 400);
            }
        }
        $course = $this->courseService->createCourse($request->validated());
        return response()->json([
            'code'    => 201,
            'message' => 'تم إنشاء الكورس بنجاح.',
            'data'    => new CourseResource($course->fresh())
        ], 201);
    }

    public function getAllCourses(Request $request): JsonResponse
    {
        $filters = $request->only([
            'name',
            'mosque_id',
            'project_id',
            'mosque_name',
            'project_name',
            'limit'
        ]);

        $courses = $this->courseService->getAllCourses($filters);
        
        $resource = CourseResource::collection($courses)->response()->getData(true);

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب قائمة الكورسات بنجاح.',
            'data'    => $resource['data'],
            'meta'    => $resource['meta'] ?? null,
            'links'   => $resource['links'] ?? null
        ], 200);
    }

    /* جلب دورة محددة بواسطة ID */
    public function getCourse(int $id): JsonResponse
    {
        $course = $this->courseService->getCourseById($id);

        if (!$course) {
            return response()->json([
                'code'    => 404,
                'message' => 'الكورس غير موجود.',
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب بيانات الكورس بنجاح.',
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
                'message' => 'الكورس غير موجود.',
                'data'    => null
            ], 404);
        }

        if (!$course->is_active) {
            return response()->json([
                'code'    => 403,
                'message' => 'هذا الكورس غير فعّال ولا يمكن تعديله.'
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
            'message' => 'تم تحديث بيانات الكورس بنجاح.',
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
                'message' => 'الكورس غير موجود.',
            ], 404);
        }

        $isDeleted = $this->courseService->deleteCourse($course);

        if ($isDeleted) {
            return response()->json([
                'code'    => 200,
                'message' => 'تم حذف الكورس بنجاح.',
            ], 200);
        }
        return response()->json([
            'code'    => 500,
            'message' => 'حدث خطأ أثناء حذف الكورس.',
        ], 500);
    }
    /* تفعيل أو أرشفة دورة (مع التأكد من وجودها أولاً) */
    public function editCourseStatus(int $id): JsonResponse
    {
        $course = $this->courseService->getCourseById((int)$id);

        if (!$course) {
            return response()->json([
                'code'    => 404,
                'message' => 'الكورس غير موجود.'
            ], 404);
        }

        $updatedCourse = $this->courseService->editCourseStatus($course);

        return response()->json([
            'code'    => 200,
            'message' => $updatedCourse->is_active ? 'تم تفعيل الكورس بنجاح' : 'تم أرشفة الكورس بنجاح',
            'data'    => new CourseResource($updatedCourse)
        ], 200);
    }

    public function getMyCourses()
    {
        $userId = Auth::id();

        $courses = $this->courseService->getMyCourses($userId);

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب كورساتي بنجاح',
            'data'    => CourseResource::collection($courses)
        ], 200);
    }
}
