<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sabr extends Model
{
    use HasFactory;

    protected $fillable = [
        'student',
        'giver',
        'course',
        'value',
        'type',
        'date',
        'parts', // الأجزاء
        'note',  // الملاحظة
    ];

    /**
     * عمل Cast لحقل الأجزاء ليتم التعامل معه كمصفوفة (Array) تلقائياً
     */
    protected $casts = [
        'parts' => 'array',
        'date'  => 'date',
    ];

    /**
     * علاقة السبر بالطالب
     */
    public function studentDetails(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student');
    }

    /**
     * علاقة السبر بالشيخ/المشرف (Giver)
     */
    public function giverDetails(): BelongsTo
    {
        return $this->belongsTo(User::class, 'giver');
    }

    /**
     * علاقة السبر بالدورة (Course)
     */
    public function courseDetails(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course');
    }
    /**
     * سكووب التصفية الديناميكية لبيانات السبر
     */
    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['course'] ?? null, function ($q, $courseId) {
            $q->where('course', $courseId);
        })
            ->when($filters['giver'] ?? null, function ($q, $giverId) {
                $q->where('giver', $giverId);
            })
            ->when($filters['student'] ?? null, function ($q, $studentId) {
                $q->where('student', $studentId);
            })
            // قراءة جماعية لحلقة كاملة في طلب واحد بدل طلب لكل طالب.
            ->when($filters['student_ids'] ?? null, function ($q, $studentIds) {
                $q->whereIn('student', (array) $studentIds);
            })
            ->when($filters['circle_id'] ?? null, function ($q, $circleId) {
                $q->whereIn('student', StudentCircle::query()
                    ->where('circle', $circleId)
                    ->select('student'));
            })
            // فصل السبور المجدولة (بانتظار النتيجة) عن المسجّلة: مرحلتان لا مرحلة واحدة.
            ->when(isset($filters['has_result']), function ($q) use ($filters) {
                filter_var($filters['has_result'], FILTER_VALIDATE_BOOLEAN)
                    ? $q->whereNotNull('value')
                    : $q->whereNull('value');
            });
    }
}
