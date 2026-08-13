<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLessonRequest;
use App\Http\Requests\UpdateLessonRequest;
use App\Http\Resources\LessonResource;
use App\IService\ILessonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LessonController extends Controller
{
    protected $lessonService;

    public function __construct(ILessonService $lessonService)
    {
        $this->lessonService = $lessonService;
    }

    /**
     * جلب كافة الدروس مع الفلترة
     */
    public function getAllLessons(Request $request): JsonResponse
    {
        $filters = $request->only(['name', 'subject_id', 'subject_name', 'limit']);

        $lessons = $this->lessonService->getAllLessons($filters);

        $resource = LessonResource::collection($lessons)->response()->getData(true);

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب قائمة الدروس بنجاح.',
            'data' => $resource['data'],
            'meta' => $resource['meta'] ?? null,
            'links' => $resource['links'] ?? null,
        ], 200);
    }

    /**
     * جلب درس محدد بواسطة ID
     */
    public function getLesson(int $id): JsonResponse
    {
        $lesson = $this->lessonService->getLessonById($id);

        if (! $lesson) {
            return response()->json([
                'code' => 404,
                'message' => 'الدرس غير موجود.',
            ], 404);
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب بيانات الدرس بنجاح.',
            'data' => new LessonResource($lesson),
        ], 200);
    }

    /**
     * إنشاء درس جديد
     */
    public function createLesson(StoreLessonRequest $request): JsonResponse
    {
        $lesson = $this->lessonService->createLesson($request->validated());

        return response()->json([
            'code' => 201,
            'message' => 'تم إنشاء الدرس بنجاح.',
            'data' => new LessonResource($lesson),
        ], 201);
    }

    /**
     * تحديث درس
     */
    public function updateLesson(Request $request, int $id): JsonResponse
    {
        $lesson = $this->lessonService->getLessonById($id);

        if (! $lesson) {
            return response()->json([
                'code' => 404,
                'message' => 'الدرس غير موجود.',
            ], 404);
        }

        // 2. التحقق اليدوي من الريكوست (Update)
        $updateRequest = app(UpdateLessonRequest::class);
        $validator = Validator::make($request->all(), $updateRequest->rules(), $updateRequest->messages());

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => $validator->errors(),
            ], 422);
        }

        $updatedLesson = $this->lessonService->updateLesson($lesson, $validator->validated());

        return response()->json([
            'code' => 200,
            'message' => 'تم تحديث بيانات الدرس بنجاح.',
            'data' => new LessonResource($updatedLesson),
        ], 200);
    }

    /**
     * حذف درس
     */
    public function deleteLesson(int $id): JsonResponse
    {
        $lesson = $this->lessonService->getLessonById($id);

        if (! $lesson) {
            return response()->json([
                'code' => 404,
                'message' => 'الدرس غير موجود.',
            ], 404);
        }

        $this->lessonService->deleteLesson($lesson);

        return response()->json([
            'code' => 200,
            'message' => 'تم حذف الدرس بنجاح.',
        ], 200);
    }
}
