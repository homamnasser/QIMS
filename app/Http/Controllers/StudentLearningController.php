<?php

namespace App\Http\Controllers;

use App\Http\Resources\AbsenceResource;
use App\Http\Resources\ExamResource;
use App\Http\Resources\MemorizationResource;
use App\Http\Resources\NoteResource;
use App\Http\Resources\ReadingImprovementResource;
use App\Http\Resources\SabrResource;
use App\Http\Resources\StudentCourseScheduleResource;
use App\Http\Resources\StudentLearningCircleResource;
use App\Http\Resources\StudentLearningCourseResource;
use App\Http\Resources\StudentMosqueResource;
use App\Http\Resources\StudentNotificationResource;
use App\Http\Resources\WarningResource;
use App\IService\IStudentLearningService;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentLearningController extends Controller
{
    public function __construct(
        private readonly IStudentLearningService $studentLearningService
    ) {}

    public function mosque(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        if (! $student) {
            return $this->studentOnlyResponse();
        }

        $mosque = $this->studentLearningService->getMosque($student);

        return response()->json([
            'code' => 200,
            'message' => $mosque
                ? 'تم جلب مسجد الطالب بنجاح.'
                : 'الطالب غير منتسب إلى مسجد حالياً.',
            'data' => $mosque ? new StudentMosqueResource($mosque) : null,
        ]);
    }

    public function circles(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        if (! $student) {
            return $this->studentOnlyResponse();
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب حلقات الطالب بنجاح.',
            'data' => StudentLearningCircleResource::collection(
                $this->studentLearningService->getCircles($student)
            ),
        ]);
    }

    public function courses(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        if (! $student) {
            return $this->studentOnlyResponse();
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب كورسات الطالب بنجاح.',
            'data' => StudentLearningCourseResource::collection(
                $this->studentLearningService->getCourses($student)
            ),
        ]);
    }

    public function courseSchedule(Request $request, int $courseId): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        if (! $student) {
            return $this->studentOnlyResponse();
        }

        $course = $this->studentLearningService->getCourseSchedule($student, $courseId);
        if (! $course) {
            return response()->json([
                'code' => 404,
                'message' => 'الكورس غير موجود ضمن كورسات الطالب المسجل فيها.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب جدول الكورس ودروسه بنجاح.',
            'data' => new StudentCourseScheduleResource($course),
        ]);
    }

    public function notes(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        if (! $student) {
            return $this->studentOnlyResponse();
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب ملاحظات الطالب بنجاح.',
            'data' => NoteResource::collection(
                $this->studentLearningService->getNotes($student)
            ),
        ]);
    }

    public function sabrs(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        if (! $student) {
            return $this->studentOnlyResponse();
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب سجلات سبر الطالب بنجاح.',
            'data' => SabrResource::collection(
                $this->studentLearningService->getSabrs($student)
            ),
        ]);
    }

    public function memorizations(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        if (! $student) {
            return $this->studentOnlyResponse();
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب سجلات تسميع الطالب بنجاح.',
            'data' => MemorizationResource::collection(
                $this->studentLearningService->getMemorizations($student)
            ),
        ]);
    }

    public function warnings(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        if (! $student) {
            return $this->studentOnlyResponse();
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب إنذارات الطالب بنجاح.',
            'data' => WarningResource::collection(
                $this->studentLearningService->getWarnings($student)
            ),
        ]);
    }

    public function exams(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        if (! $student) {
            return $this->studentOnlyResponse();
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب امتحانات الطالب بنجاح.',
            'data' => ExamResource::collection(
                $this->studentLearningService->getExams($student)
            ),
        ]);
    }

    public function readingImprovements(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        if (! $student) {
            return $this->studentOnlyResponse();
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب تقييمات قراءة الطالب بنجاح.',
            'data' => ReadingImprovementResource::collection(
                $this->studentLearningService->getReadingImprovements($student)
            ),
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        if (! $student) {
            return $this->studentOnlyResponse();
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب سجل حضور الطالب بنجاح.',
            'data' => AbsenceResource::collection(
                $this->studentLearningService->getAttendance($student)
            ),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        if (! $student) {
            return $this->studentOnlyResponse();
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب إشعارات الطالب بنجاح.',
            'data' => StudentNotificationResource::collection(
                $this->studentLearningService->getNotifications($student)
            ),
        ]);
    }

    public function readNotifications(Request $request): JsonResponse
    {
        $student = $this->authenticatedStudent($request);
        if (! $student) {
            return $this->studentOnlyResponse();
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم تعليم الإشعارات مقروءة.',
            'data' => [
                'marked' => $this->studentLearningService->markNotificationsRead($student),
            ],
        ]);
    }

    private function authenticatedStudent(Request $request): ?Student
    {
        $user = $request->user();

        return $user instanceof Student ? $user : null;
    }

    private function studentOnlyResponse(): JsonResponse
    {
        return response()->json([
            'code' => 403,
            'message' => 'هذه الواجهات متاحة لحسابات الطلاب فقط.',
            'data' => null,
        ], 403);
    }
}
