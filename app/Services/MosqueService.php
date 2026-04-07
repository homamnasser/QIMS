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




}
