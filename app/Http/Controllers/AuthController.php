<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;
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
            $student = \App\Models\Student::where('username', $credentials['email'])->first();
            if ($student) {
                $userData = [
                    'id'         => $student->id,
                    'first_name' => $student->first_name ?? $student->name,
                    'last_name'  => $student->last_name ?? '',
                    'email'      => $student->username,
                    'role'       => 'student'
                ];
            }
        }

        $cookie = cookie('qims_auth_token', $token, 60*24*7, '/', null, env('APP_ENV') === 'production', true, false, 'Strict');

        return response()->json([
            'code' => 200,
            'message' => 'تم تسجيل دخول المستخدم بنجاح',
            'data' => [
                'user'  => $userData
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

        return response()->json(['code' => 401, 'message' => 'Unauthenticated.'], 401);
    }
    /* تحديث عضو هيئة تدريس (مع التأكد من وجوده أولاً) */
    public function updateStaffMember(Request $request, int $id): JsonResponse
    {
        if ($id === 1) {
            return response()->json([
                'code'    => 403,
                'message' => 'The Super Admin account cannot be modified.',
                'data'    => null
            ], 403);
        }

        $user = $this->staffService->getStaffById($id);

        if (!$user) {
            return response()->json([
                'code'    => 404,
                'message' => 'المستخدم غير موجود!',
                'data'    => null
            ], 404);
        }

        $staffRequest = app(UpdateUserRequest::class);

        $validator = Validator::make(
            $request->all(),
            $staffRequest->rules(),
            $staffRequest->messages()
        );

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'message' => 'Validation error.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();
        $updatedUser = $this->staffService->updateStaff($user, $validatedData);

        if (isset($validatedData['role_id'])) {
            $this->staffService->assignRoleToUser($user, (int)$validatedData['role_id']);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'تم تحديث المستخدم العضو بنجاح',
            'data'    => new UserResource($user)
        ], 200);
    }
    /* حذف عضو هيئة تدريس (مع التأكد من عدم وجوده أولاً) */
    public function deleteStaffMember(int $id): JsonResponse
    {
        if ($id === 1) {
            return response()->json([
                'code'    => 403,
                'message' => 'Cannot delete the Super Admin account.',
                'data'    => null
            ], 403);
        }

        $user = $this->staffService->getStaffById($id);
        if (!$user) {
            return response()->json([
                'code'    => 404,
                'message' => 'المستخدم غير موجود',
                'data'    => null
            ], 404);
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

        $staff = $this->staffService->getAllStaff($searchTerm);

        return response()->json([
            'code'    => 200,
            'message' => 'تم جلب المستخدمين بنجاح',
            'data'    => UserResource::collection($staff)
        ], 200);
    }
}
