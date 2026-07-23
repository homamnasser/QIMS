<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignLessonsRequest;
use App\Http\Resources\CourseDateScheduleResource;
use App\IService\ICourseCurriculumService;
use App\Models\CourseDate;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\UpdateCourseCurriculumRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\IService\ICourseService;

class CourseCurriculumController extends Controller
{
    protected $curriculumService;
    protected $courseService;

    public function __construct(ICourseCurriculumService $curriculumService, ICourseService $courseService)
    {
        $this->curriculumService = $curriculumService;
        $this->courseService = $courseService;
    }

    /**
     * تعيين الدروس لموعد دورة محدد
     */
    public function assignLessonsToDate(AssignLessonsRequest $request): JsonResponse
    {
        $courseDate = CourseDate::findOrFail($request->course_date_id);

        $result = $this->curriculumService->assignLessonsToDate(
            $courseDate,
            $request->lesson_ids
        );

        return response()->json([
            'code'    => 200,
            'message' => 'تم إسناد الدروس بنجاح.',
            'data'    => new CourseDateScheduleResource($result)
        ], 200);
    }
    /* تحديث منهج دورة محددة */
    public function updateAssignLessonsToDate(Request $request, $id): JsonResponse
    {

        $existsInPivot = DB::table('course_date_lesson')
            ->where('course_date_id', $id)
            ->exists();

        if (!$existsInPivot) {
            return response()->json([
                'code'    => 404,
                'message' => 'تعذّر التحديث. لا توجد دروس مُسندة حالياً لمعرّف تاريخ الكورس (' . $id . ').'
            ], 404);
        }

        $courseDate = CourseDate::find($id);

        $updateRequest = new UpdateCourseCurriculumRequest();
        $validator = Validator::make(
            array_merge($request->all(), ['course_date_id' => $id]),
            $updateRequest->rules($id),
            $updateRequest->messages()
        );

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'status'  => 'error',
                'errors'  => $validator->errors()
            ], 422);
        }

        $result = $this->curriculumService->updateCourseCurriculum(
            $courseDate,
            $validator->validated()['lesson_ids']
        );

        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'message' => 'تم تحديث المنهج الدراسي للكورس بنجاح.',
            'data'    => new CourseDateScheduleResource($result)
        ], 200);
    }

    /* حذف جميع الدروس المعينة لتاريخ دورة معين */
    public function deleteLessonsFromDate(int $id): JsonResponse
    {
        $existsInPivot = DB::table('course_date_lesson')->where('course_date_id', $id)->exists();

        if (!$existsInPivot) {
            return response()->json([
                'code'    => 404,
                'status'  => 'error',
                'message' => 'لم يتم العثور على دروس مُسندة لهذا المعرّف.',
            ], 404);
        }

        $isDeleted = $this->curriculumService->detachAllLessons($id);

        if ($isDeleted) {
            return response()->json([
                'code'    => 200,
                'status'  => 'success',
                'message' => 'تم إزالة جميع الدروس من هذا التاريخ بنجاح.',
            ], 200);
        }

        return response()->json([
            'code'    => 500,
            'status'  => 'error',
            'message' => 'حدث خطأ أثناء عملية الحذف.',
        ], 500);
    }


    /**
     * جلب تفاصيل الدروس ليوم دوام معين (التقويم اليومي)
     */
    public function getLessonsByDate(int $id): JsonResponse
    {
        $courseDate = $this->curriculumService->getCourseDateWithLessons($id);

        if (!$courseDate) {
            return response()->json([
                'code'    => 404,
                'message' => 'تاريخ الكورس غير موجود.'
            ], 404);
        }

        if ($courseDate->lessons->isEmpty()) {
            return response()->json([
                'code'    => 200,
                'message' => 'لا توجد دروس مُسندة لهذا التاريخ.',
                'data'    => new CourseDateScheduleResource($courseDate)
            ], 200);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب المنهج اليومي بنجاح.',
            'data'    => new CourseDateScheduleResource($courseDate)
        ], 200);
    }

    /**
 * جلب التقويم الكامل لكورس معين (جميع الأيام والدروس)
 */
public function getCurriculumByCourse(int $courseId): JsonResponse
{

    $course = $this->courseService->getCourseById($courseId);

    if (!$course) {
        return response()->json([
            'code'    => 404,
            'status'  => 'error',
            'message' => 'الكورس ذو المعرّف (' . $courseId . ') غير موجود.',
        ], 404);
    }

    // 2. إذا كان الكورس مجوداً، نطلب التقويم الخاص به
    $curriculum = $this->curriculumService->getFullCourseCurriculum($courseId);

    // 3. الرد بالبيانات
    return response()->json([
        'code'    => 200,
        'status'  => 'success',
        'message' => 'تم جلب المنهج الدراسي الكامل للكورس بنجاح.',
        'data'    => CourseDateScheduleResource::collection($curriculum)
    ], 200);
}
}
