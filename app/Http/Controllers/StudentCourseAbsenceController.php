<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAbsenceRequest;
use App\Http\Requests\UpdateAbsenceRequest;
use App\Http\Resources\AbsenceResource;
use App\IService\IStudentCourseAbsenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentCourseAbsenceController extends Controller
{
    protected $absenceService;

    public function __construct(IStudentCourseAbsenceService $absenceService)
    {
        $this->absenceService = $absenceService;
    }

    /**
     * 📋 1. جلب أول (جميع الغيابات مع الفلترة الديناميكية)
     */
    public function getAllAbsence(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'student_id', 'course_id']);
        $absences = $this->absenceService->getAllAbsences($filters);

        return response()->json([
            'code'   => 200,
            'status' => 'success',
            'data'   => AbsenceResource::collection($absences)
        ], 200);
    }

    /**
     * 🚀 2. إنشاء (تسجيل غياب جديد)
     */
    public function createAbsence(StoreAbsenceRequest $request): JsonResponse
    {
        $absence = $this->absenceService->createAbsence($request->validated());
        $absence->load(['studentDetails', 'courseDetails']);

        return response()->json([
            'code'    => 201,
            'message' => 'Absence record created successfully.',
            'data'    => new AbsenceResource($absence)
        ], 201);
    }

    /**
     * 🔍 3. غيت باي ايدي (جلب تفاصيل غياب محدد)
     */
    public function getAbsenceById(int $id): JsonResponse
    {
        $absence = $this->absenceService->getAbsenceById($id);

        if (!$absence) {
            return response()->json([
                'code'    => 404,
                'status'  => 'error',
                'message' => 'Absence record not found.'
            ], 404);
        }

        $absence->load(['studentDetails', 'courseDetails']);

        return response()->json([
            'code'   => 200,
            'message' => 'Absence record retrieved successfully.',
            'data'   => new AbsenceResource($absence)
        ], 200);
    }

    /**
     * 🔄 4. ابديت (تعديل تفاصيل الغياب)
     */
    public function updateAbsence(UpdateAbsenceRequest $request, int $id): JsonResponse
    {
        $absence = $this->absenceService->getAbsenceById($id);

        if (!$absence) {
            return response()->json([
                'code'    => 404,
                'message' => 'Absence record not found.'
            ], 404);
        }

        $this->absenceService->updateAbsence($absence, $request->validated());
        $absence->load(['studentDetails', 'courseDetails']);

        return response()->json([
            'code'    => 200,
            'message' => 'Absence record updated successfully.',
            'data'    => new AbsenceResource($absence)
        ], 200);
    }

    /**
     * 🗑️ 5. ديليت (حذف سجل الغياب)
     */
    public function deleteAbsence(int $id): JsonResponse
    {
        $absence = $this->absenceService->getAbsenceById($id);

        if (!$absence) {
            return response()->json([
                'code'    => 404,
                'message' => 'Absence record not found.'
            ], 404);
        }

        $this->absenceService->deleteAbsence($absence);

        return response()->json([
            'code'    => 200,
            'message' => 'Absence record deleted successfully.'
        ], 200);
    }
}
