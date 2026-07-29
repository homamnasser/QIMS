<?php

namespace App\Services;

use App\Exceptions\MosqueHasCoursesException;
use App\Exceptions\MosqueHasScopedRecordsException;
use App\IService\IMosqueService;
use App\Models\Mosque;
use Illuminate\Database\QueryException;

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
            $query->where('name', 'LIKE', '%'.$name.'%');
        }

        return $limit ? $query->paginate($limit) : $query->get();
    }

    public function deleteMosque(int $id): bool
    {
        $mosque = Mosque::find($id);

        if (! $mosque) {
            return false;
        }

        $coursesCount = $mosque->courses()->count();
        if ($coursesCount > 0) {
            throw new MosqueHasCoursesException($coursesCount);
        }

        $scopedCounts = $this->scopedRecordCounts($mosque);
        if (array_sum($scopedCounts) > 0) {
            throw new MosqueHasScopedRecordsException(...$scopedCounts);
        }

        try {
            return (bool) $mosque->delete();
        } catch (QueryException $exception) {
            if ($this->isForeignKeyConstraintViolation($exception)) {
                $scopedCounts = $this->scopedRecordCounts($mosque);
                if (array_sum($scopedCounts) > 0) {
                    throw new MosqueHasScopedRecordsException(
                        $scopedCounts[0],
                        $scopedCounts[1],
                        $scopedCounts[2],
                        $exception,
                    );
                }

                throw new MosqueHasCoursesException(
                    $mosque->courses()->count(),
                    $exception
                );
            }

            throw $exception;
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function scopedRecordCounts(Mosque $mosque): array
    {
        return [
            $mosque->staff()->count(),
            $mosque->students()->count(),
            $mosque->surveys()->count(),
        ];
    }

    private function isForeignKeyConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23000' && $driverCode === 1451;
    }
}
