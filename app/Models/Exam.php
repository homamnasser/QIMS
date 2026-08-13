<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exam extends Model
{
    use HasFactory;

    // تحديد الحقول القابلة للتعبئة الجماعية (Mass Assignment)
    protected $fillable = [
        'student',
        'subject',
        'course',
        'mark',
        // تاريخ الاختبار كما وقع، لا لحظة كتابة الصف: نافذة التقييم تُقارَن به.
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /**
     * 🔍 الـ Scope المحلي لفلترة علامات الامتحانات ديناميكياً بداخل الـ API
     */
    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['student_id'] ?? null, function ($q, $studentId) {
            $q->where('student', $studentId);
        })
            ->when($filters['subject_id'] ?? null, function ($q, $subjectId) {
                $q->where('subject', $subjectId);
            })
            ->when($filters['course_id'] ?? null, function ($q, $courseId) {
                $q->where('course', $courseId);
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

    // =========================================================================
    // ✨ العلاقات الموحدة (Relationships) بستايل الـ Clean Code شحن تفاصيل الكيانات
    // =========================================================================

    /**
     * 🎓 علاقة الامتحان بالطالب التابع له
     */
    public function studentDetails(): BelongsTo
    {
        // تم ربط الحقل student بـ المفتاح الأساسي لجدول الطلاب id
        return $this->belongsTo(Student::class, 'student', 'id');
    }

    /**
     * 📚 علاقة الامتحان بالمادة الدراسية المعنية
     */
    public function subjectDetails(): BelongsTo
    {
        // تم ربط الحقل subject بـ المفتاح الأساسي لجدول المواد id
        return $this->belongsTo(Subject::class, 'subject', 'id');
    }

    /**
     * 🏁 علاقة الامتحان بالكورس/الدورة التابع لها
     */
    public function courseDetails(): BelongsTo
    {
        // تم ربط الحقل course بـ المفتاح الأساسي لجدول الكورسات id
        return $this->belongsTo(Course::class, 'course', 'id');
    }
}
