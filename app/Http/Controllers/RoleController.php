<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\PermissionResource;
use App\IService\IRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    protected $roleService;

    public function __construct(IRoleService $roleService)
    {
        $this->roleService = $roleService;
    }
    /* جلب كافة الأدوار مع صلاحياتها */
    public function getAllRoles(): JsonResponse
    {
        $roles = $this->roleService->getAllRoles();

        return response()->json([
            'code'    => 200,
            'message' => 'Roles retrieved successfully',
            'data'    => RoleResource::collection($roles)
        ], 200);
    }
    /* إنشاء دور جديد */
    public function createRole(StoreRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $role = $this->roleService->createRole(
            $validated['name'],
            $validated['permissions']
        );
        return response()->json([
            'code'    => 201,
            'message' => 'Role created successfully',
            'data'    => new RoleResource($role)
        ], 201);
    }
    /* جلب دور محدد بواسطة ID */
    public function getRole(int $id): JsonResponse
    {
        try {
            $role = $this->roleService->getRoleById($id);
            if (!$role) {
                return response()->json([
                    'code'    => 404,
                    'message' => 'Role not found',
                    'data'    => null
                ], 404);
            }

            return response()->json([
                'code'    => 200,
                'message' => 'Role retrieved successfully',
                'data'    => new RoleResource($role)
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'code'    => 404,
                'message' => 'Role not found',
                'data'    => null
            ], 404);
        }
    }
    /* تحديث دور (مع التأكد من وجوده أولاً) */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        try {
            $role = $this->roleService->getRoleById($id);
            if (!$role) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Role not found',
                    'data'    => null

                ], 404);
            }

            $roleRequest = app(UpdateRoleRequest::class);

            $validator = Validator::make(
                $request->all(),
                $roleRequest->rules(),
                $roleRequest->messages()
            );

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'message' => $validator->errors()
                ], 422);
            }

            // 3. جلب البيانات الموثقة
            $data = $validator->validated();

            // 4. التحديث عبر السيرفس
            $updatedRole = $this->roleService->updateRole(
                $id,
                $data['name'],
                $data['permissions']
            );

            return response()->json([
                'code' => 200,
                'message' => 'Role updated successfully',
                'data' => new RoleResource($updatedRole)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /* حذف دور */
    public function deleteRole(int $id): JsonResponse
    {
        try {
            $this->roleService->deleteRole($id);

            return response()->json([
                'code'    => 200,
                'message' => 'Role deleted successfully',
                'data'    => null
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'code'    => 404,
                'message' => 'Role not found',
                'data'    => null
            ], 404);
        }
    }

    /* جلب كافة الصلاحيات المتاحة */
    public function getAllPermissions(): \Illuminate\Http\JsonResponse
    {
        $permissions = $this->roleService->getAllPermissions();

        return response()->json([
            'code'    => 200,
            'message' => 'Permissions retrieved successfully.',
            'data'    => PermissionResource::collection($permissions)
        ], 200);
    }
}
