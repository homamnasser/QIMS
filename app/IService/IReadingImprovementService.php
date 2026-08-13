<?php

namespace App\IService;

use App\Models\ReadingImprovement;
use Illuminate\Support\Collection;

interface IReadingImprovementService
{
    public function getAll(array $filters = []): Collection;

    public function getById(int $id): ?ReadingImprovement;

    public function create(array $data): ReadingImprovement;

    public function update(ReadingImprovement $readingImprovement, array $data): bool;

    public function delete(ReadingImprovement $readingImprovement): bool;
}
