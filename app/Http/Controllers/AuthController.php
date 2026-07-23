<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Services\StaffService;
use App\IService\IStaffService;



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
            'data' => new UserResource($user)
        ], 201);
    }

    /* تسجيل دخول المستخدم */
    public function loginUser(LoginRequest $request)
    {
        $credentials = $request->validated();
        $token = $this->staffService->login($credentials);

        if (!$token) {
            return response()->json([
                'code' => 401,
                'message' => 'بريد إلكتروني أو كلمة مرور غير صحيحة'
            ], 401);
        }

        $user = null;
        if (filter_var($credentials['email'], FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $credentials['email'])->first();
        }

        $userData = null;
        if ($user) {
            $userData = new UserResource($user);
        } else {
            $student = Student::where('username', $credentials['email'])->first();
            if ($student) {
                $userData = new StudentResource($student);
            }
        }

        // Create secure HttpOnly cookie (Strict for CSRF protection)
        $cookie = cookie('qims_auth_token', $token, 60*24*7, '/', null, env('APP_ENV') === 'production', true, false, 'Strict');
        return response()->json([
            'code' => 200,
            'message' => 'تم تسجيل دخول المستخدم بنجاح',
            'data' => [
                'user'  => $userData,
                'token' => $token
            ]
        ], 200)->cookie($cookie);
    }
    /* تسجيل خروج المستخدم */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $this->staffService->logout($user)) {
            $cookie = cookie()->forget('qims_auth_token');
            return response()->json([
                'code' => 200,
                'message' => 'تم تسجيل خروج المستخدم بنجاح'
            ], 200)->withCookie($cookie);
        }

        return response()->json(['code' => 401, 'message' => 'غير مصادق.'], 401);
    }
    /* تحديث عضو هيئة تدريس (مع التأكد من وجوده أولاً) */
    public function updateStaffMember(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->staffService->getStaffById($id);

        if (!$user) {
            return response()->json([
                'code'    => 404,
                'message' => 'المستخدم غير موجود!',
                'data'    => null
            ], 404);
        }

        if ($user->isSuperAdmin()) {
            return response()->json([
                'code'    => 403,
                'message' => 'لا يمكن تعديل حساب المدير الأعلى.',
                'data'    => null
            ], 403);
        }

        $validatedData = $request->validated();
        $updatedUser = $this->staffService->updateStaff($user, $validatedData);

        return response()->json([
            'code'    => 200,
            'message' => 'تم تحديث المستخدم العضو بنجاح',
            'data'    => new UserResource($updatedUser)
        ], 200);
    }
    /* حذف عضو هيئة تدريس (مع التأكد من عدم وجوده أولاً) */
    public function deleteStaffMember(int $id): JsonResponse
    {
        $user = $this->staffService->getStaffById($id);
        if (!$user) {
            return response()->json([
                'code'    => 404,
                'message' => 'المستخدم غير موجود',
                'data'    => null
            ], 404);
        }


        if ($user->isSuperAdmin()) {
            return response()->json([
                'code'    => 403,
                'message' => 'لا يمكن حذف حساب المدير الأعلى.',
                'data'    => null
            ], 403);
        }

        $isDeleted = $this->staffService->deleteStaff($id);

        if (!$isDeleted) {
            return response()->json([
                'code'    => 400,
                'message' => 'لا يمكن حذف المستخدم: هذا المستخدم تم إسناده كمشرف على مشروع فعال.',
                'data'    => null
            ], 400);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'تم حذف المستخدم بنجاح',
            'data'    => null
        ], 200);
    }
    /* الحصول على عضو هيئة تدريس معين (مع التأكد من وجوده أولاً) */
    public function getStaffById(int $id): JsonResponse
    {
        $user = $this->staffService->getStaffById($id);

        if (!$user) {
            return response()->json([
                'code'    => 404,
                'message' => 'المستخدم غير موجود',
                'data'    => null
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب المستخدم بنجاح',
            'data'    => new UserResource($user)
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
            'code'    => 200,
            'message' => 'تم جلب المستخدمين بنجاح',
            'data'    => $resource['data'],
            'meta'    => $resource['meta'] ?? null,
            'links'   => $resource['links'] ?? null
        ], 200);
    }
}
