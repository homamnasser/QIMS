<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdministrationBehaviorObservation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'deduction_points' => 'decimal:2',
        'occurred_at' => 'datetime',
        'approved_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function candidate()
    {
        return $this->belongsTo(EvaluationCandidate::class, 'evaluation_candidate_id');
    }
}
