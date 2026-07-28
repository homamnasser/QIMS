<?php

namespace App\IService;

use Illuminate\Foundation\Auth\User as Authenticatable;

interface IMobileAuthenticationService
{
    public function login(array $credentials): ?array;

    public function refresh(Authenticatable $account): ?array;

    public function logout(Authenticatable $account): bool;
}
