<?php

namespace App\Services;

use App\IService\IMemorizationService;
use App\Models\Memorization;
use Illuminate\Support\Collection;

class MemorizationService implements IMemorizationService
{
    public function createMemorization(array $data): Memorization
    {
        return Memorization::create($data);
    }

    public function getMemorizationById(int $id): Memorization
    {
        return Memorization::find($id);
    }

    public function deleteMemorization(Memorization $memorization): bool
    {
        return $memorization->delete();
    }

    public function getMemorizationsForAccount(int $authId, bool $isStudent): Collection
    {
        $column = $isStudent ? 'student' : 'giver';

        return Memorization::where($column, $authId)
            ->with(['studentDetails', 'giverDetails'])
            ->orderBy($column === 'student' ? 'page_number' : 'created_at', $column === 'student' ? 'asc' : 'desc')
            ->get();
    }

    public function getAllMemorizations(array $filters = []): Collection
    {
        return Memorization::query()
            ->with(['studentDetails', 'giverDetails'])
            ->filter($filters) //
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
