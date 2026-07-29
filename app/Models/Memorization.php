<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Memorization extends Model
{
    use HasFactory;

    protected $fillable = [
        'student',
        'giver',
        'course_id',
        'circle_id',
        'course_date_id',
        'record_type',
        'recorded_at',
        'page_number',
        'name',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    /**
     * 🎓 علاقة التسميع بالطالب المتعلّق به
     * كل سجل تسميع ينتمي إلى طالب واحد
     */
    public function studentDetails(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student');
    }

    /**
     * 🔒 علاقة التسميع بالأستاذ (المستخدم) الذي قام بالتسميع له
     * كل سجل تسميع ينتمي إلى مستخدم (Giver) واحد
     */
    public function giverDetails(): BelongsTo
    {
        return $this->belongsTo(User::class, 'giver');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function courseDate(): BelongsTo
    {
        return $this->belongsTo(CourseDate::class);
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['student_id'] ?? null, function ($q, $studentId) {
            $q->where('student', $studentId);
        })
            ->when($filters['giver_id'] ?? null, function ($q, $giverId) {
                $q->where('giver', $giverId);
            });
    }
}
