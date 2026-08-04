<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStudentRequest;
use App\Http\Resources\StudentResource;
use App\IService\IStudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateStudentRequest;

class StudentController extends Controller
{
    protected $studentService;

    // حقن الواجهة عبر الكونستركتور
    public function __construct(IStudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    /**
     * إنشاء طالب جديد
     */

    public function createStudent(CreateStudentRequest $request): JsonResponse
    {
        $student = $this->studentService->createStudent($request->validated());

        return response()->json([
            'code'    => 201,
            'message' => 'تم إنشاء حساب الطالب وإسناده إلى دور الطالب بنجاح.',
            'data'    => new StudentResource($student)
        ], 201);
    }

    /**
     * تحديث بيانات طالب موجود
     */
    public function updateStudent(UpdateStudentRequest $request, int $id): JsonResponse
    {
        $student = $this->studentService->getStudentById((int)$id);

        if (!$student) {
            return response()->json([
                'code'    => 404,
                'message' => 'الطالب غير موجود.',
                'data'    => null
            ], 404);
        }

        $validatedData = $request->validated();
        $updatedStudent = $this->studentService->updateStudent($student, $validatedData);

        return response()->json([
            'code'    => 200,
            'message' => 'تم تحديث بيانات الطالب بنجاح.',
            'data'    => new StudentResource($updatedStudent)
        ], 200);
    }
    /* حذف طالب معين */
    public function deleteStudent(int $id): JsonResponse
    {
        $student = $this->studentService->getStudentById((int)$id);

        if (!$student) {
            return response()->json([
                'code'    => 404,
                'message' => 'الطالب غير موجود.',
            ], 404);
        }

        $this->studentService->deleteStudent($student);

        return response()->json([
            'code'    => 200,
            'message' => 'تم حذف حساب الطالب بنجاح.',
        ], 200);
    }
    /* جلب بيانات طالب معين */
    public function getStudentById(int $id): JsonResponse
    {
        $student = $this->studentService->getStudentById((int)$id);

        if (!$student) {
            return response()->json([
                'code'    => 404,
                'message' => 'الطالب غير موجود.',
                'data'    => null
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب بيانات الطالب بنجاح.',
            'data'    => new StudentResource($student)
        ], 200);
    }

    public function getAllStudents(Request $request): JsonResponse
    {
        $filters = $request->only([
            'first_name',
            'last_name',
            'academic_class',
            'reading_level',
            'parent_social_state',
            'q',
            'circle_id',
            'has_circle',
            'limit',
        ]);

        $students = $this->studentService->getAllStudents($filters);
        
        $resource = StudentResource::collection($students)->response()->getData(true);

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب قائمة الطلاب بنجاح.',
            'data'    => $resource['data'],
            'meta'    => $resource['meta'] ?? null,
            'links'   => $resource['links'] ?? null
        ], 200);
    }
}
