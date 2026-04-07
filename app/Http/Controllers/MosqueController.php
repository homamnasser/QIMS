<?php
namespace App\Http\Controllers;
use App\IService\IMosqueService;
use App\Http\Requests\MosqueRequest;
use App\Http\Resources\MosqueResource;
use Illuminate\Http\JsonResponse;

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


    // public function update(MosqueRequest $request, int $id): JsonResponse
    // {
    //     // 1. جلب الكائن أولاً
    //     $mosque = $this->mosqueService->getMosqueById($id);

    //     // 2. تمريره للسيرفس للتعديل
    //     $updatedMosque = $this->mosqueService->updateMosque($mosque, $request->validated());

    //     return response()->json([
    //         'code'    => 200,
    //         'message' => 'Mosque updated successfully.',
    //         'data'    => new MosqueResource($updatedMosque)
    //     ], 200);
    // }
}
