<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemorizationRequest;
use App\Http\Resources\MemorizationResource;
use App\IService\IMemorizationService;
use Illuminate\Http\JsonResponse;
use App\Models\Memorization;
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

        $startPage = min((int)$validated['start_page'], (int)$validated['end_page']);
        $endPage = max((int)$validated['start_page'], (int)$validated['end_page']);

        $insertedMemorizations = [];

        for ($page = $startPage; $page <= $endPage; $page++) {

            $memorization = Memorization::updateOrCreate(
                [
                    'student'     => $validated['student_id'],
                    'page_number' => $page,
                ],
                [
                    'giver'       => $giverId,
                    'name'        => $validated['name'] ?? "Page " . $page,
                ]
            );

            $memorization->load(['studentDetails', 'giverDetails']);
            $insertedMemorizations[] = $memorization;
        }

        return response()->json([
            'code'    => 201,
            'status'  => 'success',
            'message' => 'Memorization pages recorded successfully without duplication.',
            'data'    => MemorizationResource::collection($insertedMemorizations)
        ], 201);
    }

    public function getMemorizationById(int $id): JsonResponse
    {
        $memorization = $this->memorizationService->getMemorizationById($id);

        if (!$memorization) {
            return response()->json([
                'code'    => 404,
                'status'  => 'error',
                'message' => 'Memorization record not found.'
            ], 404);
        }

        $memorization->load(['studentDetails', 'giverDetails']);

        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'data'    => new MemorizationResource($memorization)
        ], 200);
    }

    public function deleteMemorization(int $id): JsonResponse
    {
        $memorization = $this->memorizationService->getMemorizationById($id);

        if (!$memorization) {
            return response()->json([
                'code'    => 404,
                'message' => 'Memorization record not found.'
            ], 404);
        }

        $this->memorizationService->deleteMemorization($memorization);

        return response()->json([
            'code'    => 200,
            'message' => 'Memorization record deleted successfully.'
        ], 200);
    }

   public function getMyMemorizations(): JsonResponse
    {
        $user = auth()->user(); // جلب كائن المستخدم الحالي 🔒
        $authId = $user->id;

        // 🌟 جلب اسم الرول باستخدام ميثود سباتي القياسية getRoleNames()
        // الدالة ترجع كولكشن أو مصفوفة، نأخذ العنصر الأول منها (مثلاً 'student' أو 'giver')
        $role = $user->getRoleNames()->first();

        // احتياطاً في حال كان الحساب لا يملك أي رول مسجلة في جداول سباتي
        if (!$role) {
            return response()->json([
                'code'    => 403,
                'status'  => 'error',
                'message' => 'This account does not have any role assigned.'
            ], 403);
        }

        // استدعاء السيرفس الذكية لتحديد الحقل وجلب البيانات بناءً على الرول الصافية
        $memorizations = $this->memorizationService->getMemorizationsByRole($authId, $role);

        if ($memorizations->isEmpty()) {
            return response()->json([
                'code'    => 200,
                'status'  => 'success',
                'message' => 'No memorization records found for your account.',
                'data'    => []
            ], 200);
        }

        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'data'    => MemorizationResource::collection($memorizations)
        ], 200);
    }

    public function getAllMemorizations(Request $request): JsonResponse
    {
        $filters = $request->only(['student_id', 'giver_id']);
        $memorizations = $this->memorizationService->getAllMemorizations($filters);

        if ($memorizations->isEmpty()) {
            return response()->json([
                'code'    => 200,
                'status'  => 'success',
                'message' => 'No memorization records found.',
                'data'    => []
            ], 200);
        }

        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'data'    => MemorizationResource::collection($memorizations)
        ], 200);
    }
}
