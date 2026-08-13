<?php

namespace App\Services;

use App\IService\ISabrService;
use App\Models\Sabr;
use Illuminate\Support\Collection;

class SabrService implements ISabrService
{
    /**
     * إنشاء سجل سبر جديد بأمان
     */
    public function createSabr(array $data): Sabr
    {
        $sabr = Sabr::create($data);

        $sabr->load(['studentDetails', 'giverDetails', 'courseDetails']);

        return $sabr;
    }

    public function updateSabrResult(int $id, array $data): Sabr
    {
        $sabr = Sabr::find($id);

        $sabr->update([
            'value' => $data['value'],
            'note' => $data['note'] ?? $sabr->note,
        ]);

        $sabr->load(['studentDetails', 'giverDetails', 'courseDetails']);

        return $sabr;
    }

    public function getSabrById(int $id): ?Sabr
    {
        return Sabr::with(['studentDetails', 'giverDetails', 'courseDetails'])->find($id);
    }

    public function getAllSabrs(array $filters = []): Collection
    {
        return Sabr::with(['studentDetails', 'giverDetails', 'courseDetails'])
            ->filter($filters) // السكووب الديناميكي من الموديل
            ->latest()
            ->get();
    }

    public function deleteSabr(Sabr $sabr): bool
    {
        return $sabr->delete();
    }
}
