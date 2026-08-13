<?php

namespace App\Services;

use App\IService\IReadingImprovementService;
use App\Models\ReadingImprovement;
use Illuminate\Support\Collection;

class ReadingImprovementService implements IReadingImprovementService
{
    /**
     * 📋 جلب كافة السجلات مع الفلترة والشحن المسبق للأسماء
     */
    public function getAll(array $filters = []): Collection
    {
        return ReadingImprovement::query()
            // 🚀 Eager Loading لضمان جلب أسماء الطالب والكورس للـ Resource بكفاءة
            ->with(['studentDetails', 'courseDetails'])
            ->filter($filters)
            ->latest()
            ->get();
    }

    /**
     * 🔍 جلب سجل محدد بواسطة الـ ID
     */
    public function getById(int $id): ?ReadingImprovement
    {
        return ReadingImprovement::query()
            ->with(['studentDetails', 'courseDetails'])
            ->find($id);
    }

    /**
     * ➕ إضافة سجل تحسن قراءة جديد
     */
    public function create(array $data): ReadingImprovement
    {
        $readingImprovement = ReadingImprovement::create($data);

        // 🔄 شحن العلاقات فوراً كي يعود الكائن محتملاً للأسماء مباشرة في الاستجابة (Response)
        return $readingImprovement->load(['studentDetails', 'courseDetails']);
    }

    /**
     * ✏️ تعديل سجل موجود
     */
    public function update(ReadingImprovement $readingImprovement, array $data): bool
    {
        return $readingImprovement->update($data);
    }

    /**
     * 🗑️ حذف سجل
     */
    public function delete(ReadingImprovement $readingImprovement): bool
    {
        return $readingImprovement->delete();
    }
}
