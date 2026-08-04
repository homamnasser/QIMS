<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warning extends Model
{
    use HasFactory;

    protected $fillable = [
        'student',
        'warner',
        'title',
        'description',
        'deduction_points',
    ];

    protected $casts = [
        'deduction_points' => 'decimal:2',
    ];

    /**
     * 🔍 Local Scope لفلترة الإنذارات بمرونة تامة
     */
    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['student_id'] ?? null, function ($q, $studentId) {
            $q->where('student', $studentId);
        })
            ->when($filters['warner_id'] ?? null, function ($q, $warnerId) {
                $q->where('warner', $warnerId);
            })
            ->when($filters['title'] ?? null, function ($q, $title) {
                $q->where('title', 'like', '%'.$title.'%');
            })
            // قراءة جماعية لحلقة كاملة في طلب واحد بدل طلب لكل طالب.
            ->when($filters['student_ids'] ?? null, function ($q, $studentIds) {
                $q->whereIn('student', (array) $studentIds);
            })
            ->when($filters['circle_id'] ?? null, function ($q, $circleId) {
                $q->whereIn('student', StudentCircle::query()
                    ->where('circle', $circleId)
                    ->select('student'));
            });
    }

    /**
     * 🎓 علاقة الإنذار بالطالب (كل إنذار يتبع لطالب واحد)
     */
    public function studentDetails(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student');
    }

    /**
     * 🔒 علاقة الإنذار بالأستاذ موجه الإنذار (كل إنذار يتبع لمستخدم واحد)
     */
    public function warnerDetails(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warner');
    }
}
