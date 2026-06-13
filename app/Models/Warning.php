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
            $q->where('title', 'like', '%' . $title . '%');
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
