<?php

namespace App\IService;

use App\Models\User;

interface IStaffService
{
    public function updateStaff(User $user, array $data): User;
    public function createStaff(array $data): User;
    public function login(array $credentials): ?string;
    public function logout(User $user): bool;
    public function assignRoleToUser(User $user, string $roleName): void;
}
