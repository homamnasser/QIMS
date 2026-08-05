<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\UpdateExamRequest;
use App\Http\Resources\ExamResource;
use App\IService\IExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    protected $examService;

    public function __construct(IExamService $examService)
    {
        $this->examService = $examService;
    }

    public function createExam(StoreExamRequest $request): JsonResponse
    {
        $exam = $this->examService->createExamMark($request->validated());

        $exam->load(['studentDetails', 'subjectDetails', 'courseDetails']);

        return response()->json([
            'code' => 201,
            'message' => 'تم تسجيل علامة الامتحان بنجاح.',
            'data' => new ExamResource($exam),
        ], 201);
    }

    public function getExamById(int $id): JsonResponse
    {
        $exam = $this->examService->getExamById($id);

        if (! $exam) {
            return response()->json([
                'code' => 404,
                'message' => 'سجل الامتحان غير موجود.',
            ], 404);
        }

        $exam->load(['studentDetails', 'subjectDetails', 'courseDetails']);

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب سجل الامتحان بنجاح.',
            'data' => new ExamResource($exam),
        ], 200);
    }

    /**
     * 🔄 4. تعديل علامة الامتحان فقط
     */
    public function updateExam(UpdateExamRequest $request, int $id): JsonResponse
    {
        $exam = $this->examService->getExamById($id);

        if (! $exam) {
            return response()->json([
                'code' => 404,
                'message' => 'سجل الامتحان غير موجود.',
            ], 404);
        }

        $this->examService->updateExamMark(
            $exam,
            $request->input('mark'),
            $request->input('occurred_at')
        );

        $exam->load(['studentDetails', 'subjectDetails', 'courseDetails']);

        return response()->json([
            'code' => 200,
            'message' => 'تم تحديث علامة الامتحان بنجاح.',
            'data' => new ExamResource($exam),
        ], 200);
    }

    /**
     * 🗑️ 5. حذف سجل الامتحان
     */
    public function deleteExam(int $id): JsonResponse
    {
        $exam = $this->examService->getExamById($id);

        if (! $exam) {
            return response()->json([
                'code' => 404,
                'message' => 'سجل الامتحان غير موجود.',
            ], 404);
        }

        // تنفيذ الحذف عبر السيرفس
        $this->examService->deleteExam($exam);

        return response()->json([
            'code' => 200,
            'message' => 'تم حذف سجل الامتحان بنجاح.',
        ], 200);
    }

    public function getAllExams(Request $request): JsonResponse
    {
        $filters = $request->only(['student_id', 'subject_id', 'course_id', 'student_ids', 'circle_id']);

        $exams = $this->examService->getAllExamMarks($filters);

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب سجلات الامتحانات بنجاح.',
            'data' => ExamResource::collection($exams),
        ], 200);
    }

    public function myExams(Request $request): JsonResponse
    {
        $filters = $request->only(['student_id', 'subject_id', 'course_id', 'student_ids', 'circle_id']);
        $exams = $this->examService->getExamMarksForTeacher(
            (int) $request->user()->id,
            $filters
        );

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'data' => ExamResource::collection($exams),
        ], 200);
    }
}
