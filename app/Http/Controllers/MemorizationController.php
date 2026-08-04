<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemorizationRequest;
use App\Http\Resources\MemorizationResource;
use App\IService\IMemorizationService;
use App\Models\Circle;
use App\Models\CourseDate;
use App\Models\Memorization;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemorizationController extends Controller
{
    protected $memorizationService;

    public function __construct(IMemorizationService $memorizationService)
    {
        $this->memorizationService = $memorizationService;
    }

    /**
     * 🚀 إنشاء سجلات تسميع لعدة صفحات بناءً على النطاق المطلوب
     */
    public function createMemorization(StoreMemorizationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $giverId = auth()->id();
        $circle = isset($validated['circle_id'])
            ? Circle::findOrFail($validated['circle_id'])
            : null;
        $courseDate = isset($validated['course_date_id'])
            ? CourseDate::findOrFail($validated['course_date_id'])
            : null;
        $courseId = $circle?->course_id
            ?? $courseDate?->course_id
            ?? ($validated['course_id'] ?? null);
        $recordType = $validated['record_type'] ?? 'memorization';
        $recordedAt = $validated['recorded_at']
            ?? $courseDate?->session_date?->startOfDay()
            ?? now();

        $startPage = min((int) $validated['start_page'], (int) $validated['end_page']);
        $endPage = max((int) $validated['start_page'], (int) $validated['end_page']);

        $insertedMemorizations = [];

        for ($page = $startPage; $page <= $endPage; $page++) {

            $identity = [
                'student' => $validated['student_id'],
                'page_number' => $page,
                'record_type' => $recordType,
            ];
            if ($courseDate) {
                $identity['course_date_id'] = $courseDate->id;
            } elseif ($circle) {
                $identity['circle_id'] = $circle->id;
            }

            $memorization = Memorization::updateOrCreate(
                $identity,
                [
                    'giver' => $giverId,
                    'course_id' => $courseId,
                    'circle_id' => $circle?->id,
                    'course_date_id' => $courseDate?->id,
                    'recorded_at' => $recordedAt,
                    'name' => $validated['name'] ?? 'Page '.$page,
                ]
            );

            $memorization->load(['studentDetails', 'giverDetails', 'course', 'circle', 'courseDate']);
            $insertedMemorizations[] = $memorization;
        }

        return response()->json([
            'code' => 201,
            'status' => 'success',
            'message' => 'تم تسجيل صفحات الحفظ بنجاح بدون تكرار.',
            'data' => MemorizationResource::collection($insertedMemorizations),
        ], 201);
    }

    public function getMemorizationById(int $id): JsonResponse
    {
        $memorization = $this->memorizationService->getMemorizationById($id);

        if (! $memorization) {
            return response()->json([
                'code' => 404,
                'status' => 'error',
                'message' => 'سجل الحفظ غير موجود.',
            ], 404);
        }

        $memorization->load(['studentDetails', 'giverDetails']);

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'data' => new MemorizationResource($memorization),
        ], 200);
    }

    public function deleteMemorization(int $id): JsonResponse
    {
        $memorization = $this->memorizationService->getMemorizationById($id);

        if (! $memorization) {
            return response()->json([
                'code' => 404,
                'message' => 'سجل الحفظ غير موجود.',
            ], 404);
        }

        $this->memorizationService->deleteMemorization($memorization);

        return response()->json([
            'code' => 200,
            'message' => 'تم حذف سجل الحفظ بنجاح.',
        ], 200);
    }

    public function getMyMemorizations(): JsonResponse
    {
        $user = auth()->user(); // جلب كائن المستخدم الحالي 🔒
        $authId = $user->id;

        if (! $user->primaryRole()) {
            return response()->json([
                'code' => 403,
                'status' => 'error',
                'message' => 'هذا الحساب لا يملك أي دور مُسنَد.',
            ], 403);
        }

        $memorizations = $this->memorizationService->getMemorizationsForAccount(
            $authId,
            $user instanceof Student
        );

        if ($memorizations->isEmpty()) {
            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'لم يتم العثور على سجلات حفظ لحسابك.',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'data' => MemorizationResource::collection($memorizations),
        ], 200);
    }

    public function getAllMemorizations(Request $request): JsonResponse
    {
        $filters = $request->only(['student_id', 'giver_id', 'student_ids', 'circle_id']);
        $memorizations = $this->memorizationService->getAllMemorizations($filters);

        if ($memorizations->isEmpty()) {
            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'لم يتم العثور على سجلات حفظ.',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'data' => MemorizationResource::collection($memorizations),
        ], 200);
    }
}
