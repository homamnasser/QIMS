<?php

namespace App\Support;

use App\Models\User;

class StaffScopeContext
{
    private ?User $staff = null;

    public function activate(User $staff): void
    {
        $this->staff = $staff->isMosqueScoped() ? $staff : null;
    }

    public function clear(): void
    {
        $this->staff = null;
    }

    public function staff(): ?User
    {
        return $this->staff;
    }
}
