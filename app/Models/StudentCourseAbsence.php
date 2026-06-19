<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCourseAbsence extends Model
{
    use HasFactory;

    protected $fillable = [
        'student',
        'course',
        'note',
        'type',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];


    /**
     * 🎓 الطالب الغائب
     */
    public function studentDetails(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student', 'id');
    }

    /**
     * 🏁 الكورس الذي تم الغياب فيه
     */
    public function courseDetails(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course', 'id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['type'] ?? null, function ($q, $type) {
            $q->where('type', $type);
        })
        ->when($filters['student_id'] ?? null, function ($q, $studentId) {
            $q->where('student', $studentId);
        })
        ->when($filters['course_id'] ?? null, function ($q, $courseId) {
            $q->where('course', $courseId);
        });
    }

}
