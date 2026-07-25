<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreReadingImprovementRequest;
use App\Http\Requests\UpdateReadingImprovementRequest;
use App\Http\Resources\ReadingImprovementResource;
use App\IService\IReadingImprovementService;
use App\Models\ReadingImprovement;
use Illuminate\Http\JsonResponse;
class ReadingImprovementController extends Controller
{
    protected IReadingImprovementService $readingImprovementService;

    public function __construct(IReadingImprovementService $readingImprovementService)
    {
        $this->readingImprovementService = $readingImprovementService;
    }

    /**
     * 📋 عرض قائمة سجلات تحسن القراءة
     */
    public function index(Request $request): JsonResponse
    {
        $records = $this->readingImprovementService->getAll($request->all());

        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'message' => 'Reading improvement records retrieved successfully.',
            'data'    => ReadingImprovementResource::collection($records)
        ], 200);
    }

    /**
     * ➕ إنشاء سجل جديد لتحسن القراءة
     */
    public function store(StoreReadingImprovementRequest $request): JsonResponse
    {
        $record = $this->readingImprovementService->create($request->validated());

        return response()->json([
            'code'    => 201,
            'status'  => 'success',
            'message' => 'Reading improvement record created successfully.',
            'data'    => new ReadingImprovementResource($record)
        ], 201);
    }

    /**
     * 🔍 عرض تفاصيل سجل محدد
     */
    public function show(int $id): JsonResponse
    {
        $record = $this->readingImprovementService->getById($id);

        if (!$record) {
            return response()->json([
                'code'    => 404,
                'status'  => 'error',
                'message' => 'Reading improvement record not found.'
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'message' => 'Reading improvement record retrieved successfully.',
            'data'    => new ReadingImprovementResource($record)
        ], 200);
    }

    /**
     * ✏️ تعديل سجل تحسن قراءة
     */
    public function update(UpdateReadingImprovementRequest $request, ReadingImprovement $readingImprovement): JsonResponse
    {
        $this->readingImprovementService->update($readingImprovement, $request->validated());

        // 🔄 إعادة شحن العلاقات لضمان ظهور أسماء الطالب والكورس المحدثة بداخل الـ Resource
        $readingImprovement->load(['studentDetails', 'courseDetails']);

        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'message' => 'Reading improvement record updated successfully.',
            'data'    => new ReadingImprovementResource($readingImprovement)
        ], 200);
    }

    /**
     * 🗑️ حذف سجل تحسن قراءة
     */
    public function destroy(ReadingImprovement $readingImprovement): JsonResponse
    {
        $this->readingImprovementService->delete($readingImprovement);

        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'message' => 'Reading improvement record deleted successfully.'
        ], 200);
    }
}
