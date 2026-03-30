<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
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

    public function createStaffMember(StoreUserRequest $request)
    {

        $user = $this->staffService->createStaff($request->validated());

        return response()->json([
            'code' => 201,
            'message' => 'User created successfully',
            'data' => new UserResource($user)
        ], 201);
    }


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

    public function updateStaffMember(UpdateUserRequest $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'code' => 404,
                'message' => 'User not found',
                'data' => []
            ], 404);
        }

        $validatedData = $request->validated();

        $updatedUser = $this->staffService->updateStaff($user, $request->validated());
         if ($request->has('role_id')) {
            $this->staffService->assignRoleToUser($user, (int)$request->role_id);
        }
        return response()->json([
            'code' => 200,
            'message' => 'User updated successfully',
            'data' => new UserResource($user)
        ], 200);
    }
}
