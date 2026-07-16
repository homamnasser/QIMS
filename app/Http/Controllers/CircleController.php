<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCircleRequest;
use App\IService\ICircleService;
use App\Http\Resources\CircleResource;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\CourseDateScheduleResource;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateCircleRequest;
use Illuminate\Support\Facades\Validator;

class CircleController extends Controller
{
    protected $circleService;

    public function __construct(ICircleService $circleService)
    {
        $this->circleService = $circleService;
    }

    public function createCircle(CreateCircleRequest $request): JsonResponse
    {
        $circle = $this->circleService->createCircle($request->validated());

        return response()->json([
            'code'    => 201,
            'message' => 'Circle created successfully.',
            'data'    => new CircleResource($circle->load(['teacher', 'course']))
        ], 201);
    }
    /**
     * Get the curriculum for the authenticated teacher's circle.
     */
    public function getMyCircleCurriculum(): JsonResponse
    {
        $teacherId = auth()->id();

        $curriculum = $this->circleService->getCircleCurriculumByTeacher($teacherId);

        if (!$curriculum) {
            return response()->json([
                'code'    => 404,
                'message' => 'No circle or curriculum found for this teacher.',
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Your circle curriculum retrieved successfully.',
            'data'    => CourseDateScheduleResource::collection($curriculum)
        ], 200);
    }
    /**
     * Get details of a specific circle by ID.
     */
    public function getCircle(int $id): JsonResponse
    {
        $circle = $this->circleService->getCircleById($id);

        if (!$circle) {
            return response()->json([
                'code'    => 404,
                'message' => 'Circle not found.',
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Circle retrieved successfully.',
            'data'    => new CircleResource($circle->load(['teacher', 'course']))
        ], 200);
    }
    /**
     * Get a list of all circles with optional filters.
     */
    public function getAllCircles(Request $request): JsonResponse
    {
        $circles = $this->circleService->getAllCircles($request->all());
        
        $resource = CircleResource::collection($circles)->response()->getData(true);

        return response()->json([
            'code'    => 200,
            'message' => 'Circles retrieved successfully.',
            'data'    => $resource['data'],
            'meta'    => $resource['meta'] ?? null,
            'links'   => $resource['links'] ?? null
        ], 200);
    }

    /**
     * Delete a circle by ID.
     */
    public function deleteCircle(int $id): JsonResponse
    {
        $circle = $this->circleService->getCircleById($id);

        if (!$circle) {
            return response()->json([
                'code'    => 404,
                'message' => 'Circle not found',
            ], 404);
        }

        $this->circleService->deleteCircle($circle);

        return response()->json([
            'code'    => 200,
            'message' => 'Circle deleted successfully.',
        ], 200);
    }
    /* تحديث حلقة (مع التأكد من وجودها أولاً) */
    public function updateCircle(Request $request, int $id): JsonResponse
    {
        $circle = $this->circleService->getCircleById((int)$id);

        if (!$circle) {
            return response()->json([
                'code'    => 404,
                'message' => 'Circle not found',
            ], 404);
        }


        $circleRequest = app(UpdateCircleRequest::class);

        $validator = Validator::make(
            $request->all(),
            $circleRequest->rules(),
            $circleRequest->messages()
        );

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'message' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        $updatedCircle = $this->circleService->updateCircle($circle, $validatedData);

        return response()->json([
            'code'    => 200,
            'message' => 'Circle updated successfully.',
            'data'    => new CircleResource($updatedCircle->load(['teacher', 'course']))
        ], 200);
    }
}
