<?php

namespace App\IService;

use App\Models\Memorization;
use Illuminate\Support\Collection;

interface IMemorizationService
{
    public function createMemorization(array $data): Memorization;

    public function getMemorizationById(int $id): Memorization;

    public function deleteMemorization(Memorization $memorization): bool;

    public function getMemorizationsForAccount(int $authId, bool $isStudent): Collection;

    public function getAllMemorizations(array $filters = []): Collection;
}
