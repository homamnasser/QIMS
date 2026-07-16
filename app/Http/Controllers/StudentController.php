<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStudentRequest;
use App\Http\Resources\StudentResource;
use App\IService\IStudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateStudentRequest;
use Illuminate\Support\Facades\Validator;

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
            'message' => 'Student created successfully and assigned to student role.',
            'data'    => new StudentResource($student)
        ], 201);
    }

    /**
     * تحديث بيانات طالب موجود
     */
    public function updateStudent(Request $request, int $id): JsonResponse
    {
        $student = $this->studentService->getStudentById((int)$id);

        if (!$student) {
            return response()->json([
                'code'    => 404,
                'message' => 'Student not found',
                'data'    => null
            ], 404);
        }

        $studentRequest = app(UpdateStudentRequest::class);

        $studentRequest->merge($request->all());


        $validator = Validator::make(
            $studentRequest->all(),
            $studentRequest->rules(),
            $studentRequest->messages()
        );

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'message' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();
        $updatedStudent = $this->studentService->updateStudent($student, $validatedData);

        return response()->json([
            'code'    => 200,
            'message' => 'Student updated successfully.',
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
                'message' => 'Student not found',
            ], 404);
        }

        $this->studentService->deleteStudent($student);

        return response()->json([
            'code'    => 200,
            'message' => 'Student deleted successfully.',
        ], 200);
    }
    /* جلب بيانات طالب معين */
    public function getStudentById(int $id): JsonResponse
    {
        $student = $this->studentService->getStudentById((int)$id);

        if (!$student) {
            return response()->json([
                'code'    => 404,
                'message' => 'Student not found',
                'data'    => null
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Student retrieved successfully.',
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
            'limit'
        ]);

        $students = $this->studentService->getAllStudents($filters);
        
        $resource = StudentResource::collection($students)->response()->getData(true);

        return response()->json([
            'code'    => 200,
            'message' => 'Students retrieved successfully.',
            'data'    => $resource['data'],
            'meta'    => $resource['meta'] ?? null,
            'links'   => $resource['links'] ?? null
        ], 200);
    }
}
