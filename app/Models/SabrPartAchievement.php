<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SabrPartAchievement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'bonus_points' => 'decimal:2',
        'first_success_at' => 'datetime',
    ];

    public function candidate()
    {
        return $this->belongsTo(EvaluationCandidate::class, 'evaluation_candidate_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
