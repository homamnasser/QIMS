<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\IService\IRoleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

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
            'code' => 200,
            'message' => 'تم جلب الأدوار بنجاح',
            'data' => RoleResource::collection($roles),
        ], 200);
    }

    /* إنشاء دور جديد */
    public function createRole(StoreRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (in_array(strtolower($validated['name']), config('roles.protected_names'), true)) {
            return response()->json([
                'code' => 403,
                'message' => 'لا يمكن إنشاء دور يحمل اسم أحد أدوار النظام الأساسية.',
            ], 403);
        }

        $role = $this->roleService->createRole(
            $validated['name'],
            $validated['role_family'],
            $validated['permissions']
        );

        return response()->json([
            'code' => 201,
            'message' => 'تم إنشاء الدور بنجاح',
            'data' => new RoleResource($role),
        ], 201);
    }

    /* جلب دور محدد بواسطة ID */
    public function getRole(int $id): JsonResponse
    {
        try {
            $role = $this->roleService->getRoleById($id);
            if (! $role) {
                return response()->json([
                    'code' => 404,
                    'message' => 'الدور غير موجود',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'code' => 200,
                'message' => 'تم جلب بيانات الدور بنجاح',
                'data' => new RoleResource($role),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'code' => 404,
                'message' => 'الدور غير موجود',
                'data' => null,
            ], 404);
        }
    }

    /* تحديث دور (مع التأكد من وجوده أولاً) */
    public function updateRole(UpdateRoleRequest $request, int $id): JsonResponse
    {
        try {
            $role = $this->roleService->getRoleById($id);
            if (! $role) {
                return response()->json([
                    'code' => 404,
                    'message' => 'الدور غير موجود',
                    'data' => null,
                ], 404);
            }

            if ($role->is_system) {
                return response()->json([
                    'code' => 403,
                    'message' => 'هذا الدور من أدوار النظام الأساسية ومحمي من التعديل.',
                ], 403);
            }

            // 3. جلب البيانات الموثقة
            $data = $request->validated();

            // 4. التحديث عبر السيرفس
            $updatedRole = $this->roleService->updateRole(
                $id,
                $data['name'] ?? $role->name,
                $data['role_family'] ?? null,
                $data['permissions'] ?? $role->permissions->pluck('id')->all()
            );

            return response()->json([
                'code' => 200,
                'message' => 'تم تحديث الدور بنجاح',
                'data' => new RoleResource($updatedRole),
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
            $role = $this->roleService->getRoleById($id);
            if (! $role) {
                return response()->json([
                    'code' => 404,
                    'message' => 'الدور غير موجود',
                    'data' => null,
                ], 404);
            }

            if ($role->is_system) {
                return response()->json([
                    'code' => 403,
                    'message' => 'لا يمكن حذف أدوار النظام الأساسية.',
                ], 403);
            }

            $this->roleService->deleteRole($id);

            return response()->json([
                'code' => 200,
                'message' => 'تم حذف الدور بنجاح',
                'data' => null,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'code' => 404,
                'message' => 'الدور غير موجود',
                'data' => null,
            ], 404);
        }
    }

    /* جلب كافة الصلاحيات المتاحة */
    public function getAllPermissions(): \Illuminate\Http\JsonResponse
    {
        $permissions = $this->roleService->getAllPermissions();

        return response()->json([
            'code' => 200,
            'message' => 'تم جلب الصلاحيات بنجاح.',
            'data' => PermissionResource::collection($permissions),
        ], 200);
    }
}
