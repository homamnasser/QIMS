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

        return $user;
    }

    public function createStaff(array $data): User
    {
        return User::create($data);
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
}
