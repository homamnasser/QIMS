<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStudentRequest;
use App\Http\Resources\StudentResource;
use App\IService\IStudentService;
use Illuminate\Http\JsonResponse;

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
}
