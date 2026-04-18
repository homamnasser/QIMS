<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'min_marks',
        'max_marks',
        'course_id',
        'shared_with_subject_id',
        'pdf_path'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function sharedSubject()
    {
        return $this->belongsTo(Subject::class, 'shared_with_subject_id');
    }
    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['name'] ?? null, function ($q, $name) {
            $q->where('name', 'like', '%' . $name . '%');
        })
            ->when($filters['course_id'] ?? null, function ($q, $courseId) {
                $q->where('course_id', $courseId);
            })
            ->when($filters['course_name'] ?? null, function ($q, $courseName) {
                $q->whereHas('course', function ($q) use ($courseName) {
                    $q->where('name', 'like', '%' . $courseName . '%');
                });
            });
    }
}
