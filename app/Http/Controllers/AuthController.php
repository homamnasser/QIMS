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
            'message' => 'User created successfully',
            'data' => new UserResource($user)
        ], 201);
    }

    /* تسجيل دخول المستخدم */
    public function loginUser(LoginRequest $request)
    {
        $token = $this->staffService->login($request->validated());

        if (!$token) {
            return response()->json([
                'code' => 401,
                'message' => 'The email or password provided is incorrect.'
            ], 401);
        }

        return response()->json([
            'code' => 200,
            'message' => 'User logged in successfully.',
            'data' => [
                'token' => $token
            ]
        ], 200);
    }
    /* تسجيل خروج المستخدم */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $this->staffService->logout($user)) {
            return response()->json([
                'code' => 200,
                'message' => 'User successfully signed out'
            ], 200);
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
                'message' => 'Staff member not found.',
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
            'message' => 'Staff member updated successfully.',
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
                'message' => 'Staff member not found.',
                'data'    => null
            ], 404);
        }

        $isDeleted = $this->staffService->deleteStaff($id);

        if (!$isDeleted) {
            return response()->json([
                'code'    => 400,
                'message' => 'Cannot delete staff: This user is assigned as a supervisor to existing projects.',
                'data'    => null
            ], 400);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Staff member deleted successfully.',
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
                'message' => 'Staff member not found.',
                'data'    => null
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Staff member retrieved successfully.',
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
            'message' => 'Staff members retrieved successfully.',
            'data'    => UserResource::collection($staff)
        ], 200);
    }
}
