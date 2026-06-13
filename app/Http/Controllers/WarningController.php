<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarningRequest;
use App\Http\Resources\WarningResource;
use App\IService\IWarningService;
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
            'code'    => 201,
            'message' => 'Warning recorded successfully.',
            'data'    => new WarningResource($warning)
        ], 201);
    }

    /**
     * 🔍 2. عرض تفاصيل إنذار محدد بواسطة الـ ID
     */
    public function getWarningById(int $id): JsonResponse
    {
        $warning = $this->warningService->getWarningById($id);

        if (!$warning) {
            return response()->json([
                'code'    => 404,
                'message' => 'Warning record not found.'
            ], 404);
        }

        $warning->load(['studentDetails', 'warnerDetails']);

        return response()->json([
            'code'    => 200,
            'message' => 'Warning retrieved successfully.',
            'data'    => new WarningResource($warning)
        ], 200);
    }


    public function getAllWarnigns(Request $request): JsonResponse
    {
        $filters = $request->only(['student_id', 'warner_id', 'title']);
        $warnings = $this->warningService->getAllWarnings($filters);

        if ($warnings->isEmpty()) {
            return response()->json([
                'code'    => 200,
                'message' => 'No warning records found.',
            ], 200);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Warnings retrieved successfully.',
            'data'    => WarningResource::collection($warnings)
        ], 200);
    }

    public function deleteWarning(int $id): JsonResponse
    {
        $warning = $this->warningService->getWarningById($id);

        if (!$warning) {
            return response()->json([
                'code'    => 404,
                'message' => 'Warning record not found.'
            ], 404);
        }

        if ((int)$warning->warner !== (int)auth()->id()) {
            return response()->json([
                'code'    => 403,
                'message' => 'Unauthorized! You can only delete warnings that you have created yourself.'
            ], 403);
        }

        $this->warningService->deleteWarning($warning);

        return response()->json([
            'code'    => 200,
            'message' => 'Warning record deleted successfully.'
        ], 200);
    }

    public function getMyWarnings(Request $request): JsonResponse
    {
        $user = auth()->user();

        $isStudent = $user->hasRole('student');

        $warnings = $this->warningService->getUserWarnings($user->id, $isStudent);

        if ($warnings->isEmpty()) {
            return response()->json([
                'code'    => 200,
                'status'  => 'success',
                'message' => 'No warnings found for your account.',
                'data'    => []
            ], 200);
        }

        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'data'    => WarningResource::collection($warnings)
        ], 200);
    }
}
