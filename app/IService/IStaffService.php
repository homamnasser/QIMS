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
    public function deleteStaff(int $id): bool;
    public function getStaffById(int $id): ?User;
    public function getAllStaff(?string $firstName = null);
    }
