<?php
namespace App\IService;
use App\Models\Mosque;
use Illuminate\Support\Collection;

interface IMosqueService
{
    public function getAllMosques(?string $name = null);
    public function createMosque(array $data): Mosque;
    public function updateMosque(Mosque $mosque, array $data): Mosque;
    public function deleteMosque(int $id): bool;
    public function getMosqueById(int $id): ?Mosque;
}
