<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingImprovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'student',
        'course',
        'type',
        'description',
    ];

    // =========================================================================
    // ✨ العلاقات المباشرة (BelongsTo)
    // =========================================================================

    /**
     * 🎓 تفاصيل الطالب
     */
    public function studentDetails(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student', 'id');
    }

    /**
     * 🏁 تفاصيل الكورس
     */
    public function courseDetails(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course', 'id');
    }

    // =========================================================================
    // 🔍 Local Scopes للفلترة الديناميكية
    // =========================================================================

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['student_id'] ?? null, function ($q, $studentId) {
            $q->where('student', $studentId);
        })
        ->when($filters['course_id'] ?? null, function ($q, $courseId) {
            $q->where('course', $courseId);
        })
        ->when($filters['type'] ?? null, function ($q, $type) {
            $q->where('type', $type);
        });
    }
}
