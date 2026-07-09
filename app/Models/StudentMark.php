<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StudentMark extends Model
{
    use HasFactory;
    protected $fillable = [
        'teacher',
        'student',
        'course',
        'circle',
        'attendance_marks',
        'memorization_marks',
        'reading_improvement_marks',
        'exams_marks',
        'behavior_teacher_marks',
        'behavior_admin_marks',
        'sabr_bonus',
        'total_marks',
    ];

    /**
     * 👨‍🏫 المدرس صاحب صحيفة العلامات
     */ 
    public function teacherDetails(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher', 'id');
    }

    /**
     * 🎓 الطالب صاحب صحيفة العلامات
     */
    public function studentDetails(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student', 'id');
    }

    /**
     * 🏁 الكورس التابع له العلامات
     */
    public function courseDetails(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course', 'id');
    }

    /**
     * ⭕ الحلقة التابع لها الطالب
     */
    public function circleDetails(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle', 'id');
    }
}

