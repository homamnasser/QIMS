<?php

namespace App\Services;

use App\Models\User;
use App\IService\IStaffService;
use Illuminate\Support\Facades\Auth;

class StaffService implements IStaffService
{
    public function updateStaff(User $user, array $data): User
    {
        $user->update($data);
        if (isset($data['role_id'])) {
            $user->assignRole((int)$data['role_id']);
        }
        return $user;
    }

    public function createStaff(array $data): User
    {
        $user = User::create($data);

        if (isset($data['role_id'])) {
            $user->assignRole((int)$data['role_id']);
        }

        return $user;
    }


    public function login(array $credentials): ?string
    {
        if (!Auth::attempt($credentials)) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        return $user->createToken('API TOKEN')->plainTextToken;
    }

    public function logout(User $user): bool
    {
        $user->tokens()->delete();
        return true;
    }

    public function assignRoleToUser(User $user, $roleId): void
    {
        $user->assignRole((int)$roleId);
    }

    public function getStaffById(int $id): ?User
    {
        return User::with('roles')->find($id);
    }

    public function deleteStaff(int $id): bool
    {
        $user = User::find($id);


        if (!$user || $user->projects()->exists()) {
            return false;
        }

        return $user->delete();
    }

    public function getAllStaff(?string $name = null)
    {
        return User::with('roles')
            ->when($name, function ($query, $name) {
                return $query->where(function ($q) use ($name) {
                    $q->where('first_name', 'LIKE', '%' . $name . '%')
                        ->orWhere('last_name', 'LIKE', '%' . $name . '%');
                });
            })
            ->orderBy('id', 'desc')
            ->get();
    }
}
