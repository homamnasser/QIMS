<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuranPeriodAssessment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'pages_completed' => 'decimal:2',
        'revision_pages' => 'decimal:2',
        'target_pages_snapshot' => 'decimal:2',
        'below_minimum' => 'boolean',
        'assessed_at' => 'datetime',
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
