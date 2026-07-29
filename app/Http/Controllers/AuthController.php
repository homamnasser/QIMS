<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\IService\IStaffService;
use App\Models\Mosque;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $staffService;

    public function __construct(IStaffService $staffService)
    {
        $this->staffService = $staffService;
    }

    /* إنشاء عضو هيئة تدريس جديد */
    public function createStaffMember(StoreUserRequest $request)
    {

        $user = $this->staffService->createStaff($request->validated());

        return response()->json([
            'code' => 201,
            'message' => 'تم إنشاء مستخدم جديد',
            'data' => new UserResource($user),
        ], 201);
    }

    public function staffScopeOptions(Request $request): JsonResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $mosques = Mosque::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب خيارات نطاق عمل الموظفين بنجاح.',
            'data' => [
                'work_scope_locked' => $staff->isMosqueScoped(),
                'work_scope' => $staff->isMosqueScoped()
                    ? 'mosque'
                    : 'institute',
                'mosque_id' => $staff->mosque_id,
                'mosques' => $mosques,
            ],
        ]);
    }

    /* تحديث عضو هيئة تدريس (مع التأكد من وجوده أولاً) */
    public function updateStaffMember(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->staffService->getStaffById($id);

        if (! $user) {
            return response()->json([
                'code' => 404,
                'message' => 'المستخدم غير موجود!',
                'data' => null,
            ], 404);
        }

        if ($user->isSuperAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => 'لا يمكن تعديل حساب المدير الأعلى.',
                'data' => null,
            ], 403);
        }

        $validatedData = $request->validated();
        $updatedUser = $this->staffService->updateStaff($user, $validatedData);

        return response()->json([
            'code' => 200,
            'message' => 'تم تحديث المستخدم العضو بنجاح',
            'data' => new UserResource($updatedUser),
        ], 200);
    }

    /* حذف عضو هيئة تدريس (مع التأكد من عدم وجوده أولاً) */
    public function deleteStaffMember(int $id): JsonResponse
    {
        $user = $this->staffService->getStaffById($id);
        if (! $user) {
            return response()->json([
                'code' => 404,
                'message' => 'المستخدم غير موجود',
                'data' => null,
            ], 404);
        }

        if ($user->isSuperAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => 'لا يمكن حذف حساب المدير الأعلى.',
                'data' => null,
            ], 403);
        }

        $isDeleted = $this->staffService->deleteStaff($id);

        if (! $isDeleted) {
            return response()->json([
                'code' => 400,
                'message' => 'لا يمكن حذف المستخدم: هذا المستخدم تم إسناده كمشرف على مشروع فعال.',
                'data' => null,
            ], 400);
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم حذف المستخدم بنجاح',
            'data' => null,
        ], 200);
    }

    /* الحصول على عضو هيئة تدريس معين (مع التأكد من وجوده أولاً) */
    public function getStaffById(int $id): JsonResponse
    {
        $user = $this->staffService->getStaffById($id);

        if (! $user) {
            return response()->json([
                'code' => 404,
                'message' => 'المستخدم غير موجود',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب المستخدم بنجاح',
            'data' => new UserResource($user),
        ], 200);
    }

    /* الحصول على جميع أعضاء هيئة التدريس (مع دعم الفلترة بالاسم) */
    public function getAllStaff(Request $request): JsonResponse
    {
        $searchTerm = $request->query('name');
        $limit = $request->query('limit');

        $staff = $this->staffService->getAllStaff($searchTerm, $limit);

        $resource = UserResource::collection($staff)->response()->getData(true);

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب المستخدمين بنجاح',
            'data' => $resource['data'],
            'meta' => $resource['meta'] ?? null,
            'links' => $resource['links'] ?? null,
        ], 200);
    }
}
