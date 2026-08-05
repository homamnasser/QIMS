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
        'evaluation_candidate_id',
        'evaluation_period_id',
        'evaluator_id',
        'type',
        'baseline_score',
        'final_score',
        'baseline_level',
        'final_level',
        'difference',
        'points',
        'promotion_recommended',
        'status',
        'rule_trace',
        'description',
        // تاريخ التقييم القرائي كما جرى، لا لحظة تسجيله.
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'baseline_score' => 'decimal:2',
        'final_score' => 'decimal:2',
        'difference' => 'decimal:2',
        'promotion_recommended' => 'boolean',
        'rule_trace' => 'array',
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

    public function evaluationCandidate(): BelongsTo
    {
        return $this->belongsTo(EvaluationCandidate::class);
    }

    public function evaluationPeriod(): BelongsTo
    {
        return $this->belongsTo(EvaluationPeriod::class);
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
