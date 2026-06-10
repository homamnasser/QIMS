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
            });
    }
}
