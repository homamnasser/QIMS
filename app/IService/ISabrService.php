<?php

namespace App\IService;

use App\Models\Sabr;
use Illuminate\Support\Collection;

interface ISabrService
{
    public function createSabr(array $data): Sabr;
    public function updateSabrResult(int $id, array $data): Sabr;
    public function getSabrById(int $id): ?Sabr;
    public function getAllSabrs(array $filters = []): Collection;
    public function deleteSabr(Sabr $sabr): bool;
}
