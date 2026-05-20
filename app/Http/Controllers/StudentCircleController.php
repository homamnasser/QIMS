<?php

namespace App\Http\Controllers;

use App\IService\IStudentCircleService;
use App\IService\ICircleService;
use App\Http\Resources\StudentCircleResource;
use App\Models\StudentCircle;
use App\Http\Requests\AssignStudentRequest;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\RemoveStudentRequest;
use App\IService\IStudentService;

class StudentCircleController extends Controller
{
    protected $studentCircleService;
    protected $circleService;
    protected $studentService;

    // حقن الخدمات عبر الـ Interfaces لضمان الـ Loose Coupling
    public function __construct(
        IStudentCircleService $studentCircleService,
        ICircleService $circleService,
        IStudentService $studentService
    ) {
        $this->studentCircleService = $studentCircleService;
        $this->circleService = $circleService;
        $this->studentService = $studentService;
    }

    /**
     * 1. إضافة طلاب إلى الحلقة
     */
    public function addStudents(AssignStudentRequest $request): JsonResponse
    {
        // التحقق من وجود الحلقة أولاً (404)
        $circle = $this->circleService->getCircleById((int)$request->circle_id);

        if (!$circle) {
            return response()->json([
                'code'    => 404,
                'message' => 'Circle not found.',
                'data'    => null
            ], 404);
        }

        $result = $this->studentCircleService->addStudentsToCircle($circle->id, $request->student_ids);

        $newRecords = StudentCircle::where('circle', $circle->id)
            ->whereIn('student', $result['students']->pluck('id'))
            ->with(['studentDetails', 'circleDetails'])
            ->get();

        return response()->json([
            'code'    => 200,
            'message' => "Students processed successfully. Skipped/Invalid records: {$result['skipped_count']}.",
            'data'    => StudentCircleResource::collection($newRecords)
        ], 200);
    }

    /**
     * 2. حذف طالب من حلقة محددة (باستخدام الـ Form Request المخصص)
     */
    public function removeStudent(RemoveStudentRequest $request): JsonResponse
    {

        $circle = $this->circleService->getCircleById((int)$request->circle_id);
        if (!$circle) {
            return response()->json([
                'code'    => 404,
                'message' => 'Circle not found.',
                'data'    => null
            ], 404);
        }

        $student = $this->studentService->getStudentById((int)$request->student_id);
        if (!$student) {
            return response()->json([
                'code'    => 404,
                'message' => 'Student not found.',
                'data'    => null
            ], 404);
        }

        $deleted = $this->studentCircleService->removeStudentFromCircle($circle->id, $student->id);

        if (!$deleted) {
            return response()->json([
                'code'    => 404,
                'message' => 'Student is not assigned to this circle.',
                'data'    => null
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Student removed from circle successfully.',
        ], 200);
    }

    /**
     * 3. جلب طلاب حلقة معينة
     */
    public function getStudents(int $circleId): JsonResponse
    {
        $circle = $this->circleService->getCircleById((int)$circleId);

        if (!$circle) {
            return response()->json([
                'code'    => 404,
                'message' => 'Circle not found.',
                'data'    => null
            ], 404);
        }

        $records = StudentCircle::where('circle', $circle->id)
            ->with(['studentDetails', 'circleDetails'])
            ->get();

        return response()->json([
            'code'    => 200,
            'message' => 'Circle students retrieved successfully.',
            'data'    => StudentCircleResource::collection($records)
        ], 200);
    }
}
