<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'session_date',
        'status',
        'counts_for_attendance',
        'held_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'session_date' => 'date:Y-m-d',
        'counts_for_attendance' => 'boolean',
        'held_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function evaluationPeriod()
    {
        return $this->belongsTo(EvaluationPeriod::class);
    }

    public function lessons()
    {
        return $this->belongsToMany(Lesson::class, 'course_date_lesson')
            ->using(CourseDateLesson::class)
            ->withTimestamps();
    }
}
