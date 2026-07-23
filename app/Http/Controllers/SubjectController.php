<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\IService\ISubjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;


class SubjectController extends Controller
{
    protected $subjectService;

    public function __construct(ISubjectService $subjectService)
    {
        $this->subjectService = $subjectService;
    }
    /* جلب كافة المواد مع الفلترة */
    public function createSubject(StoreSubjectRequest $request): JsonResponse
    {

        $data = $request->validated();

        if ($request->hasFile('pdf')) {
            $data['pdf_path'] = $request->file('pdf')->store('subjects/pdfs', 'public');
        }

        $subject = $this->subjectService->createSubject($data);

        return response()->json([
            'code'    => 201,
            'message' => 'تم إنشاء المادة الدراسية بنجاح.',
            'data'    => new SubjectResource($subject->fresh())
        ], 201);
    }
    /* جلب كافة المواد مع الفلترة */
    public function updateSubject(Request $request, int $id): JsonResponse
    {
        $subject = $this->subjectService->getSubjectById((int)$id);

        if (!$subject) {
            return response()->json([
                'code'    => 404,
                'message' => 'المادة الدراسية غير موجودة.',
                'data'    => null
            ], 404);
        }

        $subjectRequest = app(UpdateSubjectRequest::class);

        $validator = Validator::make(
            $request->all(),
            $subjectRequest->rules(),
            $subjectRequest->messages()
        );

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'message' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        if ($request->hasFile('pdf')) {
            if ($subject->pdf_path) {
                Storage::disk('public')->delete($subject->pdf_path);
            }
            $validatedData['pdf_path'] = $request->file('pdf')->store('subjects/pdfs', 'public');
        }

        $updatedSubject = $this->subjectService->updateSubject($subject, $validatedData);

        return response()->json([
            'code'    => 200,
            'message' => 'تم تحديث المادة الدراسية بنجاح.',
            'data'    => new SubjectResource($updatedSubject->fresh())
        ], 200);
    }
    /*حذف مادة معينة مع التأكد من وجودها أولاً*/
    public function deleteSubject(int $id): JsonResponse
    {
        $subject = $this->subjectService->getSubjectById((int)$id);

        if (!$subject) {
            return response()->json([
                'code'    => 404,
                'message' => 'المادة الدراسية غير موجودة.',
            ], 404);
        }

        if ($subject->pdf_path) {
            Storage::disk('public')->delete($subject->pdf_path);
        }

        $this->subjectService->deleteSubject($subject);

        return response()->json([
            'code'    => 200,
            'message' => 'تم حذف المادة الدراسية بنجاح.',
        ], 200);
    }

    /* جلب مادة معينة مع التأكد من وجودها أولاً */
    public function getSubject(int $id): JsonResponse
    {
        $subject = $this->subjectService->getSubjectById((int)$id);

        if (!$subject) {
            return response()->json([
                'code'    => 404,
                'message' => 'المادة الدراسية غير موجودة.',
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب بيانات المادة الدراسية بنجاح.',
            'data'    => new SubjectResource($subject)
        ], 200);
    }

    public function getAllSubjects(Request $request): JsonResponse
    {
        $filters = $request->only([
            'name',
            'course_id',
            'course_name',
            'limit'
        ]);

        $subjects = $this->subjectService->getAllSubjects($filters);
        
        $resource = SubjectResource::collection($subjects)->response()->getData(true);

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب قائمة المواد الدراسية بنجاح.',
            'data'    => $resource['data'],
            'meta'    => $resource['meta'] ?? null,
            'links'   => $resource['links'] ?? null
        ], 200);
    }
}
