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
}
