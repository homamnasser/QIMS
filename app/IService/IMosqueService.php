<?php

namespace App\IService;

use App\Models\Mosque;

interface IMosqueService
{
    public function getAllMosques(?string $name = null, $limit = null);

    public function createMosque(array $data): Mosque;

    public function updateMosque(Mosque $mosque, array $data): Mosque;

    public function deleteMosque(int $id): bool;

    public function getMosqueById(int $id): ?Mosque;
}
