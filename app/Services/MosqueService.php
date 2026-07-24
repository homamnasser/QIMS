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

    public function getAllMosques(?string $name = null, $limit = null)
    {
        $query = Mosque::query();

        if ($name) {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }

        return $limit ? $query->paginate($limit) : $query->get();
    }

    public function deleteMosque(int $id): bool
    {
        $mosque = Mosque::find($id);

        if (!$mosque) {
            return false;
        }

        $coursesCount = $mosque->courses()->count();
        if ($coursesCount > 0) {
            throw new \App\Exceptions\MosqueHasCoursesException($coursesCount);
        }

        try {
            return (bool) $mosque->delete();
        } catch (\Illuminate\Database\QueryException $exception) {
            if ($this->isForeignKeyConstraintViolation($exception)) {
                throw new \App\Exceptions\MosqueHasCoursesException(
                    $mosque->courses()->count(),
                    $exception,
                );
            }

            throw $exception;
        }
    }

    private function isForeignKeyConstraintViolation(\Illuminate\Database\QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23000' && $driverCode === 1451;
    }
}
