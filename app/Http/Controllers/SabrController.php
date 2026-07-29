<?php

namespace App\Http\Controllers;

use App\IService\ISabrService;
use App\Http\Requests\StoreSabrRequest;
use App\Http\Resources\SabrResource;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\UpdateSabrResultRequest;
use Illuminate\Http\Request;
use App\Models\Student;
class SabrController extends Controller
{
    protected $sabrService;

    public function __construct(ISabrService $sabrService)
    {
        $this->sabrService = $sabrService;
    }

    /**
     * إنشاء سجل سبر جديد
     */
    public function createSabr(StoreSabrRequest $request): JsonResponse
    {
        $sabr = $this->sabrService->createSabr($request->validated());

        return response()->json([
            'code'    => 201,
            'message' => 'تم إنشاء سجل السبر بنجاح.',
            'data'    => new SabrResource($sabr)
        ], 201);
    }

    public function updateResult(UpdateSabrResultRequest $request, int $id): JsonResponse
    {
        $sabr = $this->sabrService->getSabrById($id);

        if (!$sabr) {
            return response()->json([
                'code'    => 404,
                'message' => 'سجل السبر المطلوب غير موجود.'
            ], 404);
        }


        $user = auth()->user();
        if (
            (int) $sabr->giver !== (int) $user->id
            && ! $user->hasFullFieldOperationsAccess()
        ) {
            return response()->json([
                'code'    => 403,
                'message' => 'غير مصرّح لك بتحديث هذا السجل لأنه يعود لمعلم آخر.'
            ], 403);
        }

        $sabr->update([
            'value' => $request->validated()['value'],
            'note'  => $request->validated()['note'] ?? $sabr->note,
        ]);

        return response()->json([
            'code'    => 200,
            'message' => 'تم تحديث نتيجة وملاحظة السبر بنجاح.',
            'data'    => new SabrResource($sabr)
        ], 200);
    }

    public function getSabrById(int $id): JsonResponse
    {
        $sabr = $this->sabrService->getSabrById($id);

        if (!$sabr) {
            return response()->json([
                'code'    => 404,
                'message' => 'السبر غير موجود.'
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب بيانات السبر بنجاح.',
            'data'    => new SabrResource($sabr)
        ], 200);
    }

    public function getAllSabrs(Request $request): JsonResponse
    {
        $filters = $request->only(['course', 'giver', 'student']);

        $sabrs = $this->sabrService->getAllSabrs($filters);

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب قائمة السبور بنجاح.',
            'data'    => SabrResource::collection($sabrs)
        ], 200);
    }

    public function deleteSabr(int $id): JsonResponse
    {
        $sabr = $this->sabrService->getSabrById($id);

        if (!$sabr) {
            return response()->json([
                'code'    => 404,
                'message' => 'سجل السبر المطلوب غير موجود.'
            ], 404);
        }


        if (!is_null($sabr->value)) {
            return response()->json([
                'code'    => 400,
                'message' => 'لا يمكن حذف هذا السجل لأنه تم إسناد درجة/نتيجة إليه مسبقاً.'
            ], 400);
        }

        $this->sabrService->deleteSabr($sabr);

        return response()->json([
            'code'    => 200,
            'message' => 'تم حذف سجل السبر بنجاح.'
        ], 200);
    }

    public function getMySabrs(Request $request): JsonResponse
    {
        $user = auth()->user();
        $filters = [];

        if ($user instanceof Student) {
            $filters['student'] = $user->id;
        } else {
            $filters['giver'] = $user->id;
        }

        $sabrs = $this->sabrService->getAllSabrs($filters);

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب قائمة السبور بنجاح.',
            'data'    => SabrResource::collection($sabrs)
        ], 200);
    }
}
