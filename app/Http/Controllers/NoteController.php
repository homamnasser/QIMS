<?php

namespace App\Http\Controllers;
use App\Http\Requests\CreateNoteRequest;
use App\Http\Resources\NoteResource;
use App\IService\INoteService;
use App\IService\IStudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class NoteController extends Controller
{
    protected $noteService;
    protected $studentService;

    // حقن الخدمات عبر الـ Interfaces للحفاظ على بنية الـ Clean Architecture
    public function __construct(
        INoteService $noteService,
        IStudentService $studentService
    ) {
        $this->noteService = $noteService;
        $this->studentService = $studentService;
    }

    /**
     * 1. إنشاء ملاحظة جديدة
     * جلب معرف الأستاذ من الـ Auth تلقائياً لحماية العملية أمنياً
     */
    public function createNote(CreateNoteRequest $request): JsonResponse
    {
        // جلب معرف الأستاذ/المشرف الحالي من الـ Auth بأمان
        $userId = auth()->id();

        // استدعاء السيرفس لإنشاء الملاحظة وحقن الـ User ID
        $note = $this->noteService->createNote($request->validated(), $userId);

        // تحميل العلاقات لضمان ظهور أسماء الطلاب والأساتذة داخل الـ Resource
        $note->load(['student', 'author']);

        return response()->json([
            'code'    => 201,
            'status'  => 'success',
            'message' => 'Student note created successfully.',
            'data'    => new NoteResource($note)
        ], 201);
    }

    /**
     * 2. جلب جميع ملاحظات طالب معين
     */
    public function getNotesByStudentId(int $studentId): JsonResponse
    {
        // الاستفادة من سيرفس الطلاب للتأكد من وجود الطالب أولاً (404)
        $student = $this->studentService->getStudentById($studentId);

        if (!$student) {
            return response()->json([
                'code'    => 404,
                'message' => 'Student not found.',
                'data'    => null
            ], 404);
        }

        // جلب الملاحظات الخاصة بالطالب
        $notes = $this->noteService->getNotesByStudentId($student->id);

        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'message' => 'Student notes retrieved successfully.',
            'data'    => NoteResource::collection($notes)
        ], 200);
    }

    /**
     * 3. حذف ملاحظة
     * تمرير الـ userId المأخوذ من الـ Auth للسيرفس لمنع أي أستاذ من حذف ملاحظة غيره
     */
    public function deleteNote(int $id): JsonResponse
    {
        // جلب أيدي المستخدم الحالي
        $userId = auth()->id();

        // تنفيذ عملية الحذف الأمنية المباشرة
        $deleted = $this->noteService->deleteNote($id, $userId);

        if (!$deleted) {
            return response()->json([
                'code'    => 403,
                'message' => 'Unauthorized or note not found.',
                'data'    => null
            ], 403);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Note deleted successfully.',
        ], 200);
    }

    /**
     * 3. 🔑 جلب الملاحظات التي كتبها الأستاذ الحالي (الخاصة بي)
     */
    public function getMyNotes(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $filters = [];

        if ($request->has('student_id')) {
            $studentId = $request->query('student_id');
            $student = $this->studentService->getStudentById($studentId);

            if (!$student) {
                return response()->json([
                    'code'    => 404,
                    'status'  => 'error',
                    'message' => 'Student not found.',
                    'data'    => null
                ], 404);
            }

            $filters['student_id'] = $student->id;
        }

        $notes = $this->noteService->getNotesByTeacher($userId, $filters);

        $message = isset($filters['student_id'])
            ? 'Student notes retrieved successfully.'
            : 'Your created notes retrieved successfully.';

        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'message' => $message,
            'data'    => NoteResource::collection($notes)
        ], 200);
    }
}
