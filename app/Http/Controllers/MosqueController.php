<?php

namespace App\Http\Controllers;

use App\IService\IMosqueService;
use App\Http\Requests\MosqueRequest;
use App\Http\Resources\MosqueResource;
use App\Models\Mosque;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MosqueController extends Controller
{
    protected $mosqueService;

    public function __construct(IMosqueService $mosqueService)
    {
        $this->mosqueService = $mosqueService;
    }




    public function createMosque(MosqueRequest $request): JsonResponse
    {
        $mosque = $this->mosqueService->createMosque($request->validated());

        return response()->json([
            'code'    => 201,
            'message' => 'Mosque has been created successfully.',
            'data'    => new MosqueResource($mosque)
        ], 201);
    }


    public function updateMosque(Request $request, int $id): JsonResponse
    {
        $mosque = $this->mosqueService->getMosqueById($id);

        if (!$mosque) {
            return response()->json([
                'code'    => 404,
                'message' => 'Mosque not found.',
                'data'    => null
            ], 404);
        }

        $mosqueRequest = app(MosqueRequest::class);

        $validator = Validator::make(
            $request->all(),
            $mosqueRequest->rules(),
            $mosqueRequest->messages()
        );

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'message' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();
        $updatedMosque = $this->mosqueService->updateMosque($mosque, $validatedData);

        return response()->json([
            'code'    => 200,
            'message' => 'Mosque updated successfully.',
            'data'    => new MosqueResource($updatedMosque)
        ], 200);
    }


    public function getMosque(int $id): JsonResponse
    {
        $mosque = $this->mosqueService->getMosqueById($id);

        if (!$mosque) {
            return response()->json([
                'code'    => 404,
                'message' => 'Mosque not found.',
                'data'    => null
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Mosque retrieved successfully.',
            'data'    => new MosqueResource($mosque)
        ], 200);
    }

    public function getAllMosques(Request $request): JsonResponse
    {
        $name = $request->query('name');

        $mosques = $this->mosqueService->getAllMosques($name);

        return response()->json([
            'code'    => 200,
            'message' => 'Data retrieved successfully.',
            'data'    => MosqueResource::collection($mosques)
        ], 200);
    }
    public function deleteMosque(int $id): JsonResponse
    {
        $mosque = $this->mosqueService->getMosqueById($id);

        if (!$mosque) {
            return response()->json([
                'code'    => 404,
                'message' => 'Mosque not found.',
                'data'    => null
            ], 404);
        }

        $this->mosqueService->deleteMosque($id);

        return response()->json([
            'code'    => 200,
            'message' => 'Mosque deleted successfully.',
            'data'    => null
        ], 200);
    }
}
