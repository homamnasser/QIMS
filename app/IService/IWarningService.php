<?php

namespace App\IService;

use App\Models\Warning;
use Illuminate\Database\Eloquent\Collection;

interface IWarningService
{
    public function createWarning(array $data): Warning;

    public function getWarningById(int $id): ?Warning;

    public function deleteWarning(Warning $warning): bool;

    public function getAllWarnings(array $filters = []): Collection;

    public function getUserWarnings(int $userId, bool $isStudent): Collection;
}
