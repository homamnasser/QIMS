<?php
namespace App\IService;
use App\Models\Mosque;
use Illuminate\Support\Collection;

interface IMosqueService
{
    // public function getAllMosques():Collection;
    public function createMosque(array $data): Mosque;
    public function updateMosque(Mosque $mosque, array $data): Mosque;
    // public function deleteMosque(string $id): bool;
    // public function getMosque(string $id): ?Mosque;
}
