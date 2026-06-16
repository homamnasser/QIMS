<?php

namespace App\Http\Controllers;

use App\IService\IExamService;
use App\Http\Requests\StoreExamRequest;
use App\Http\Resources\ExamResource;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\UpdateExamRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'code'    => 201,
            'message' => 'Exam mark recorded successfully.',
            'data'    => new ExamResource($exam)
        ], 201);
    }
    public function getExamById(int $id): JsonResponse
    {
        $exam = $this->examService->getExamById($id);

        if (!$exam) {
            return response()->json([
                'code'    => 404,
                'message' => 'Exam record not found.'
            ], 404);
        }

        $exam->load(['studentDetails', 'subjectDetails', 'courseDetails']);

        return response()->json([
            'code'    => 200,
            'message' => 'Exam record retrieved successfully.',
            'data'    => new ExamResource($exam)
        ], 200);
    }

    /**
     * 🔄 4. تعديل علامة الامتحان فقط
     */
    public function updateExam(UpdateExamRequest $request, int $id): JsonResponse
    {
        $exam = $this->examService->getExamById($id);

        if (!$exam) {
            return response()->json([
                'code'    => 404,
                'message' => 'Exam record not found.'
            ], 404);
        }

        $this->examService->updateExamMark($exam, $request->input('mark'));

        $exam->load(['studentDetails', 'subjectDetails', 'courseDetails']);

        return response()->json([
            'code'    => 200,
            'message' => 'Exam mark updated successfully.',
            'data'    => new ExamResource($exam)
        ], 200);
    }

    /**
     * 🗑️ 5. حذف سجل الامتحان
     */
    public function deleteExam(int $id): JsonResponse
    {
        $exam = $this->examService->getExamById($id);

        if (!$exam) {
            return response()->json([
                'code'    => 404,
                'message' => 'Exam record not found.'
            ], 404);
        }

        // تنفيذ الحذف عبر السيرفس
        $this->examService->deleteExam($exam);

        return response()->json([
            'code'    => 200,
            'message' => 'Exam record deleted successfully.'
        ], 200);
    }

    public function getAllExams(Request $request): JsonResponse
    {
        $filters = $request->only(['student_id', 'subject_id', 'course_id']);

        $exams = $this->examService->getAllExamMarks($filters);

        return response()->json([
            'code'   => 200,
            'message' => 'Exam records retrieved successfully.',
            'data'   => ExamResource::collection($exams)
        ], 200);
    }
    
    public function myExams(): JsonResponse
    {
        $studentId = Auth::id();

        if (!$studentId) {
            return response()->json([
                'code'    => 401,
                'status'  => 'error',
                'message' => 'Unauthorized. Student is not logged in.'
            ], 401);
        }

        $exams = $this->examService->getAllExamMarks(['student_id' => $studentId]);

        return response()->json([
            'code'   => 200,
            'status' => 'success',
            'data'   => \App\Http\Resources\ExamResource::collection($exams)
        ], 200);
    }
}
