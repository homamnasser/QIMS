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
        'course_date_id',
        'circle_id',
        'note',
        'type',
        'is_excused',
        'source',
        'external_reference',
        'captured_at',
        'recorded_by',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
        'is_excused' => 'boolean',
        'captured_at' => 'datetime',
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

    public function courseDate(): BelongsTo
    {
        return $this->belongsTo(CourseDate::class);
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
        })
        // جلسة واحدة لحلقة كاملة في طلب واحد بدل طلب لكل طالب:
        // عمود الحالة يعرض حضور اليوم المحدد فقط، لا كل تواريخ الكورس.
        ->when($filters['date'] ?? null, function ($q, $date) {
            $q->whereDate('date', $date);
        })
        ->when($filters['student_ids'] ?? null, function ($q, $studentIds) {
            $q->whereIn('student', (array) $studentIds);
        });
    }

}
