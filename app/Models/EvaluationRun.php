<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_preview' => 'boolean',
        'policy_snapshot' => 'array',
        'readiness_snapshot' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function cycle()
    {
        return $this->belongsTo(EvaluationCycle::class, 'evaluation_cycle_id');
    }

    public function results()
    {
        return $this->hasMany(EvaluationResult::class);
    }
}
