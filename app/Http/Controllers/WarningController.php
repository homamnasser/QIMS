<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarningRequest;
use App\Http\Resources\WarningResource;
use App\IService\IWarningService;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarningController extends Controller
{
    protected $warningService;

    public function __construct(IWarningService $warningService)
    {
        $this->warningService = $warningService;
    }

    public function createWarning(StoreWarningRequest $request): JsonResponse
    {
        $warning = $this->warningService->createWarning($request->validated());

        $warning->load(['studentDetails', 'warnerDetails']);

        return response()->json([
            'code' => 201,
            'message' => 'تم تسجيل الإنذار بنجاح.',
            'data' => new WarningResource($warning),
        ], 201);
    }

    /**
     * 🔍 2. عرض تفاصيل إنذار محدد بواسطة الـ ID
     */
    public function getWarningById(int $id): JsonResponse
    {
        $warning = $this->warningService->getWarningById($id);

        if (! $warning) {
            return response()->json([
                'code' => 404,
                'message' => 'سجل الإنذار غير موجود.',
            ], 404);
        }

        $warning->load(['studentDetails', 'warnerDetails']);

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب بيانات الإنذار بنجاح.',
            'data' => new WarningResource($warning),
        ], 200);
    }

    public function getAllWarnings(Request $request): JsonResponse
    {
        $filters = $request->only(['student_id', 'warner_id', 'title', 'student_ids', 'circle_id']);
        $warnings = $this->warningService->getAllWarnings($filters);

        if ($warnings->isEmpty()) {
            return response()->json([
                'code' => 200,
                'message' => 'لم يتم العثور على سجلات إنذارات.',
            ], 200);
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب قائمة الإنذارات بنجاح.',
            'data' => WarningResource::collection($warnings),
        ], 200);
    }

    public function deleteWarning(int $id): JsonResponse
    {
        $warning = $this->warningService->getWarningById($id);

        if (! $warning) {
            return response()->json([
                'code' => 404,
                'message' => 'سجل الإنذار غير موجود.',
            ], 404);
        }

        $user = auth()->user();
        if (
            (int) $warning->warner !== (int) $user->id
            && ! $user->hasFullFieldOperationsAccess()
        ) {
            return response()->json([
                'code' => 403,
                'message' => 'غير مصرّح! يمكنك فقط حذف الإنذارات التي أنشأتها بنفسك.',
            ], 403);
        }

        $this->warningService->deleteWarning($warning);

        return response()->json([
            'code' => 200,
            'message' => 'تم حذف سجل الإنذار بنجاح.',
        ], 200);
    }

    public function getMyWarnings(Request $request): JsonResponse
    {
        $user = auth()->user();

        $isStudent = $user instanceof Student;

        $warnings = $this->warningService->getUserWarnings($user->id, $isStudent);

        if ($warnings->isEmpty()) {
            return response()->json([
                'code' => 200,
                'status' => 'success',
                'message' => 'لم يتم العثور على إنذارات لحسابك.',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'data' => WarningResource::collection($warnings),
        ], 200);
    }
}
