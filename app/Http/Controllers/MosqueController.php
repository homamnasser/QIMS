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



    /* إنشاء مسجد جديد */
    public function createMosque(MosqueRequest $request): JsonResponse
    {
        $mosque = $this->mosqueService->createMosque($request->validated());

        return response()->json([
            'code'    => 201,
            'message' => 'تم إنشاء المسجد بنجاح.',
            'data'    => new MosqueResource($mosque)
        ], 201);
    }

    /* تحديث مسجد (مع التأكد من وجوده أولاً) */
    public function updateMosque(Request $request, int $id): JsonResponse
    {
        $mosque = $this->mosqueService->getMosqueById($id);

        if (!$mosque) {
            return response()->json([
                'code'    => 404,
                'message' => 'المسجد غير موجود.',
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
            'message' => 'تم تحديث بيانات المسجد بنجاح.',
            'data'    => new MosqueResource($updatedMosque)
        ], 200);
    }

    /* الحصول على مسجد معين (مع التأكد من وجوده أولاً) */
    public function getMosque(int $id): JsonResponse
    {
        $mosque = $this->mosqueService->getMosqueById($id);

        if (!$mosque) {
            return response()->json([
                'code'    => 404,
                'message' => 'المسجد غير موجود.',
                'data'    => null
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب بيانات المسجد بنجاح.',
            'data'    => new MosqueResource($mosque)
        ], 200);
    }
    public function getAllMosques(Request $request): JsonResponse
    {
        $name = $request->query('name');
        $limit = $request->query('limit');

        $mosques = $this->mosqueService->getAllMosques($name, $limit);
        
        $resource = MosqueResource::collection($mosques)->response()->getData(true);

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب البيانات بنجاح.',
            'data'    => $resource['data'],
            'meta'    => $resource['meta'] ?? null,
            'links'   => $resource['links'] ?? null
        ], 200);
    }
    /* حذف مسجد (مع التأكد من وجوده أولاً) */
    public function deleteMosque(int $id): JsonResponse
    {
        $mosque = $this->mosqueService->getMosqueById($id);

        if (!$mosque) {
            return response()->json([
                'code'    => 404,
                'message' => 'المسجد غير موجود.',
                'data'    => null
            ], 404);
        }

        $this->mosqueService->deleteMosque($id);

        return response()->json([
            'code'    => 200,
            'message' => 'تم حذف المسجد بنجاح.',
            'data'    => null
        ], 200);
    }
}
