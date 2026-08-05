<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'user_id',
        'title',
        'description',
        // تاريخ الملاحظة كما وقعت، لا لحظة تدوينها.
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }


    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['student_id'] ?? null, function ($q, $studentId) {
            $q->where('student_id', $studentId);
        })
            // قراءة جماعية لحلقة كاملة في طلب واحد بدل طلب لكل طالب.
            ->when($filters['student_ids'] ?? null, function ($q, $studentIds) {
                $q->whereIn('student_id', (array) $studentIds);
            })
            ->when($filters['circle_id'] ?? null, function ($q, $circleId) {
                $q->whereIn('student_id', StudentCircle::query()
                    ->where('circle', $circleId)
                    ->select('student'));
            });
    }
}
