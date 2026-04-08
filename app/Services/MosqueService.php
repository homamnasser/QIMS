<?php

namespace App\Services;

use App\Models\Mosque;
use App\IService\IMosqueService;
use Illuminate\Support\Collection;

class MosqueService implements IMosqueService
{

    public function createMosque(array $data): Mosque
    {
        return Mosque::create($data);
    }


    public function updateMosque(Mosque $mosque, array $data): Mosque
    {   
        $mosque->update($data);
        return $mosque;
    }

    public function getMosqueById(int $id): ?Mosque
    {
        return Mosque::find($id);
    }

    public function getAllMosques(?string $name = null)
    {
        $query = Mosque::query();

        if ($name) {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }

        return $query->get();
    }

    public function deleteMosque(int $id): bool
    {
        return Mosque::where('id', $id)->delete();
    }
}
