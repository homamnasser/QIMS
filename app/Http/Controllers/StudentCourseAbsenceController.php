<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAbsenceRequest;
use App\Http\Requests\UpdateAbsenceRequest;
use App\Http\Resources\AbsenceResource;
use App\IService\IStudentCourseAbsenceService;
use App\Models\CourseDate;
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
        $filters = $request->only(['type', 'student_id', 'course_id', 'date', 'student_ids']);
        $absences = $this->absenceService->getAllAbsences($filters);

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'data' => AbsenceResource::collection($absences),
        ], 200);
    }

    /**
     * 🚀 2. إنشاء (تسجيل غياب جديد)
     */
    public function createAbsence(StoreAbsenceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['course_date_id'] = $this->sessionIdFor($data['course'], $data['date']);

        $absence = $this->absenceService->createAbsence($data);
        $absence->load(['studentDetails', 'courseDetails']);

        return response()->json([
            'code' => 201,
            'message' => 'تم تسجيل الغياب بنجاح.',
            'data' => new AbsenceResource($absence),
        ], 201);
    }

    /**
     * 🔍 3. غيت باي ايدي (جلب تفاصيل غياب محدد)
     */
    public function getAbsenceById(int $id): JsonResponse
    {
        $absence = $this->absenceService->getAbsenceById($id);

        if (! $absence) {
            return response()->json([
                'code' => 404,
                'status' => 'error',
                'message' => 'سجل الغياب غير موجود.',
            ], 404);
        }

        $absence->load(['studentDetails', 'courseDetails']);

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب سجل الغياب بنجاح.',
            'data' => new AbsenceResource($absence),
        ], 200);
    }

    /**
     * 🔄 4. ابديت (تعديل تفاصيل الغياب)
     */
    public function updateAbsence(UpdateAbsenceRequest $request, int $id): JsonResponse
    {
        $absence = $this->absenceService->getAbsenceById($id);

        if (! $absence) {
            return response()->json([
                'code' => 404,
                'message' => 'سجل الغياب غير موجود.',
            ], 404);
        }

        $data = $request->validated();
        $data['course_date_id'] = $this->sessionIdFor($absence->course, $data['date']);

        $this->absenceService->updateAbsence($absence, $data);
        $absence->load(['studentDetails', 'courseDetails']);

        return response()->json([
            'code' => 200,
            'message' => 'تم تحديث سجل الغياب بنجاح.',
            'data' => new AbsenceResource($absence),
        ], 200);
    }

    /**
     * 🔗 ربط السجل بجلسة الكورس.
     *
     * `AttendanceCalculator` يطابق أولاً على `course_date_id` ولا يرجع إلى
     * التاريخ إلا للسجلات القديمة. والطلب يتحقق أصلاً من أن التاريخ جلسة صالحة
     * لهذا الكورس، فالاشتقاق هنا لا يحتاج حقلاً جديداً من العميل ولا يمكن أن
     * يتعارض مع التاريخ المحفوظ.
     */
    private function sessionIdFor(int $courseId, string $date): ?int
    {
        $sessionId = CourseDate::query()
            ->where('course_id', $courseId)
            ->whereDate('session_date', $date)
            ->value('id');

        return $sessionId ? (int) $sessionId : null;
    }

    /**
     * 🗑️ 5. ديليت (حذف سجل الغياب)
     */
    public function deleteAbsence(int $id): JsonResponse
    {
        $absence = $this->absenceService->getAbsenceById($id);

        if (! $absence) {
            return response()->json([
                'code' => 404,
                'message' => 'سجل الغياب غير موجود.',
            ], 404);
        }

        $this->absenceService->deleteAbsence($absence);

        return response()->json([
            'code' => 200,
            'message' => 'تم حذف سجل الغياب بنجاح.',
        ], 200);
    }
}
