<?php

namespace App\Services;

use App\IService\IWarningService;
use App\Models\Warning;
use Illuminate\Database\Eloquent\Collection;

class WarningService implements IWarningService
{
    public function createWarning(array $data): Warning
    {
        return Warning::create($data);
    }

    public function getWarningById(int $id): ?Warning
    {
        return Warning::find($id);
    }

    public function getAllWarnings(array $filters = []): Collection
    {
        return Warning::query()
            ->with(['studentDetails', 'warnerDetails'])
            ->filter($filters)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function deleteWarning(Warning $warning): bool
    {
        return $warning->delete();
    }

    /**
     * 🔔 جلب الإنذارات الخاصة بالمستخدم الحالي بناءً على دوره (طالب أو موجه)
     */
    /**
     * 🔔 جلب الإنذارات الخاصة بالمستخدم بناءً على فحص السباتي
     */
    public function getUserWarnings(int $userId, bool $isStudent): Collection
    {
        $query = Warning::query()->with(['studentDetails', 'warnerDetails']);

        if ($isStudent) {
            // إذا كان طالب، نبحث عن الإنذارات الموجهة له في حقل student
            $query->where('student', $userId);
        } else {
            // إذا كان أستاذ أو موجه، نبحث عن الإنذارات التي أصدرها هو في حقل warner
            $query->where('warner', $userId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
