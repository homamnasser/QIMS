<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationCriterionResult extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_applicable' => 'boolean',
        'score' => 'decimal:2',
        'maximum_score' => 'decimal:2',
        'inputs' => 'array',
        'rule_trace' => 'array',
        'warnings' => 'array',
    ];

    public function result()
    {
        return $this->belongsTo(EvaluationResult::class, 'evaluation_result_id');
    }
}
