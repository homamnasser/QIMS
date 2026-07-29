<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherPeriodEvaluation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'behavior_score' => 'decimal:2',
        'participation_score' => 'decimal:2',
        'teacher_opinion_score' => 'decimal:2',
        'total_score' => 'decimal:2',
        'evidence' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function candidate()
    {
        return $this->belongsTo(EvaluationCandidate::class, 'evaluation_candidate_id');
    }

    public function period()
    {
        return $this->belongsTo(EvaluationPeriod::class, 'evaluation_period_id');
    }
}
