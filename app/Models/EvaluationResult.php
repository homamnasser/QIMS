<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationResult extends Model
{
    protected $guarded = [];

    protected $casts = [
        'base_score' => 'decimal:2',
        'base_maximum' => 'decimal:2',
        'bonus_score' => 'decimal:2',
        'final_score' => 'decimal:2',
        'final_percentage' => 'decimal:2',
        'is_excellent' => 'boolean',
        'excellence_checks' => 'array',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function run()
    {
        return $this->belongsTo(EvaluationRun::class, 'evaluation_run_id');
    }

    public function candidate()
    {
        return $this->belongsTo(EvaluationCandidate::class, 'evaluation_candidate_id');
    }

    public function criteria()
    {
        return $this->hasMany(EvaluationCriterionResult::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }
}
