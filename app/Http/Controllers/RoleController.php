<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\IService\IRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    protected $roleService;

    public function __construct(IRoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function getAllRoles(): JsonResponse
    {
        $roles = $this->roleService->getAllRoles();

        return response()->json([
            'code'    => 200,
            'message' => 'Roles retrieved successfully',
            'data'    => RoleResource::collection($roles)
        ], 200);
    }

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

    public function getRole(int $id): JsonResponse
    {
        try {
            $role = $this->roleService->getRoleById($id);
            if (!$role) throw new ModelNotFoundException();

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

    public function updateRole(UpdateRoleRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();

            $role = $this->roleService->updateRole(
                $id,
                $validatedData['name'],
                $validatedData['permissions']
            );

            return response()->json([
                'code'    => 200,
                'message' => 'Role updated successfully',
                'data'    => new RoleResource($role)
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'code'    => 404,
                'message' => 'Role not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'message' => $e->getMessage(),
            ], 500);
        }
    }




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
}
