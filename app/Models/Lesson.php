<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'date', 'start_page', 'end_page', 'subject_id'];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['name'] ?? null, function ($q, $name) {
            $q->where('name', 'like', '%' . $name . '%');
        })
            ->when($filters['subject_id'] ?? null, function ($q, $subjectId) {
                $q->where('subject_id', $subjectId);
            })
            ->when($filters['subject_name'] ?? null, function ($q, $subjectName) {
                $q->whereHas('subject', function ($q) use ($subjectName) {
                    $q->where('name', 'like', '%' . $subjectName . '%');
                });
            });
    }
}
