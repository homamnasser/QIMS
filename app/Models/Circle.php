<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Circle extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'teacher_id', 'course_id'];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['name'] ?? null, function ($q, $name) {
            // البحث حسب اسم الحلقة
            $q->where('name', 'like', '%' . $name . '%');
        })
            ->when($filters['teacher_id'] ?? null, function ($q, $teacherId) {
                // البحث حسب ID الأستاذ
                $q->where('teacher_id', $teacherId);
            })
            ->when($filters['teacher_name'] ?? null, function ($q, $teacherName) {
                // البحث حسب اسم الأستاذ (الأول أو الأخير) في جدول المستخدمين
                $q->whereHas('teacher', function ($q) use ($teacherName) {
                    $q->where('first_name', 'like', '%' . $teacherName . '%')
                        ->orWhere('last_name', 'like', '%' . $teacherName . '%');
                });
            })
            ->when($filters['course_id'] ?? null, function ($q, $courseId) {
                // البحث حسب ID الكورس
                $q->where('course_id', $courseId);
            })
            ->when($filters['course_name'] ?? null, function ($q, $courseName) {
                // البحث حسب اسم الكورس في جدول الكورسات
                $q->whereHas('course', function ($q) use ($courseName) {
                    $q->where('name', 'like', '%' . $courseName . '%');
                });
            });
    }
    public function circleMarks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentMark::class, 'circle', 'id');
    }
    public function registeredMarks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentMark::class, 'teacher', 'id');
    }
}
