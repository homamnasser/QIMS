<?php

namespace App\Http\Controllers;

use App\IService\ISabrService;
use App\Http\Requests\StoreSabrRequest;
use App\Http\Resources\SabrResource;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\UpdateSabrResultRequest;
use Illuminate\Http\Request;
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
            'message' => 'Sabr record created successfully.',
            'data'    => new SabrResource($sabr)
        ], 201);
    }

    public function updateResult(UpdateSabrResultRequest $request, int $id): JsonResponse
    {
        $sabr = $this->sabrService->getSabrById($id);

        if (!$sabr) {
            return response()->json([
                'code'    => 404,
                'message' => 'The requested Sabr record was not found.'
            ], 404);
        }


        if ($sabr->giver !== auth()->id()) {
            return response()->json([
                'code'    => 403,
                'message' => 'You are not authorized to update this record as it belongs to another teacher.'
            ], 403);
        }

        $sabr->update([
            'value' => $request->validated()['value'],
            'note'  => $request->validated()['note'] ?? $sabr->note,
        ]);

        return response()->json([
            'code'    => 200,
            'message' => 'Sabr value and note updated successfully.',
            'data'    => new SabrResource($sabr)
        ], 200);
    }

    public function getSabrById(int $id): JsonResponse
    {
        $sabr = $this->sabrService->getSabrById($id);

        if (!$sabr) {
            return response()->json([
                'code'    => 404,
                'message' => 'Sabr not found.'
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Sabr retrieved successfully.',
            'data'    => new SabrResource($sabr)
        ], 200);
    }

    public function getAllSabrs(Request $request): JsonResponse
    {
        $filters = $request->only(['course', 'giver', 'student']);

        $sabrs = $this->sabrService->getAllSabrs($filters);

        return response()->json([
            'code'    => 200,
            'message' => 'Sabrs retrieved successfully.',
            'data'    => SabrResource::collection($sabrs)
        ], 200);
    }

    public function deleteSabr(int $id): JsonResponse
    {
        $sabr = $this->sabrService->getSabrById($id);

        if (!$sabr) {
            return response()->json([
                'code'    => 404,
                'message' => 'The requested Sabr record was not found.'
            ], 404);
        }


        if (!is_null($sabr->value)) {
            return response()->json([
                'code'    => 400,
                'message' => 'This record cannot be deleted because a grade/value has already been assigned.'
            ], 400);
        }

        $this->sabrService->deleteSabr($sabr);

        return response()->json([
            'code'    => 200,
            'message' => 'Sabr record deleted successfully.'
        ], 200);
    }

    public function getMySabrs(Request $request): JsonResponse
    {
        $user = auth()->user();
        $filters = [];

        if ($user->hasRole('student')) {
            $filters['student'] = $user->id;
        } else {
            $filters['giver'] = $user->id;
        }

        $sabrs = $this->sabrService->getAllSabrs($filters);

        return response()->json([
            'code'    => 200,
            'message' => 'Sabrs retrieved successfully.',
            'data'    => SabrResource::collection($sabrs)
        ], 200);
    }
}
