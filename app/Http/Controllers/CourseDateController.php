<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseDateRequest;
use App\Http\Requests\StoreManualCourseDateRequest;
use App\Http\Resources\CourseDateResource;
use App\IService\ICourseDateService;
use App\IService\ICourseService;
use Illuminate\Http\JsonResponse;

class CourseDateController extends Controller
{
    protected $courseDateService;

    protected $courseService;

    public function __construct(ICourseDateService $courseDateService, ICourseService $courseService)
    {
        $this->courseDateService = $courseDateService;
        $this->courseService = $courseService;
    }

    /** إنشاء تواريخ الدوام لدورة معينة بناءً على الأيام والتواريخ المحددة */
    public function createCourseDate(StoreCourseDateRequest $request): JsonResponse
    {
        $result = $this->courseDateService->generateCourseDates(
            $request->course_id,
            $request->validated()
        );

        if (! $result) {
            return response()->json([
                'code' => 400,
                'message' => 'لا توجد تواريخ جديدة تطابق اختيارك أو أن جميع التواريخ موجودة مسبقاً.',
            ], 400);
        }

        return response()->json([
            'code' => 201,
            'message' => 'تم توليد تواريخ الكورس بنجاح.',
            'data' => CourseDateResource::collection($result),
        ], 201);
    }

    /** جلب تواريخ الدوام حسب الدورة */
    public function getDateByCourse($courseId): JsonResponse
    {
        $course = $this->courseService->getCourseById($courseId);

        if (! $course) {
            return response()->json([
                'code' => 404,
                'message' => 'الكورس غير موجود.',
            ], 404);
        }

        $dates = $this->courseDateService->getDatesByCourse($courseId);

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب التواريخ بنجاح.',
            'data' => CourseDateResource::collection($dates),
        ], 200);
    }

    /** حذف تاريخ دوام معين */
    public function deleteDate(int $id): JsonResponse
    {
        $courseDate = $this->courseDateService->getCourseDateById($id);
        if (! $courseDate) {
            return response()->json([
                'code' => 404,
                'message' => 'تاريخ الكورس غير موجود.',
            ], 404);
        }

        $this->courseDateService->deleteDate($courseDate);

        return response()->json([
            'code' => 200,
            'message' => 'تم حذف التاريخ بنجاح.',
        ], 200);
    }

    /** إضافة تاريخ دوام يدوي لدورة معينة */
    public function createDateManual(StoreManualCourseDateRequest $request): JsonResponse
    {
        $date = $this->courseDateService->addManualDate($request->validated());

        return response()->json([
            'code' => 201,
            'message' => 'تمت إضافة تاريخ الكورس يدوياً بنجاح.',
            'data' => new CourseDateResource($date),
        ], 201);
    }
}
