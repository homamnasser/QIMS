<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationExamResult extends Model
{
    protected $guarded = [];

    protected $casts = [
        'score' => 'decimal:2',
        'maximum_score' => 'decimal:2',
        'weight' => 'decimal:3',
        'assessed_at' => 'datetime',
    ];

    public function candidate()
    {
        return $this->belongsTo(EvaluationCandidate::class, 'evaluation_candidate_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
