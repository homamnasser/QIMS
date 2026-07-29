<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationPeriod extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function cycle()
    {
        return $this->belongsTo(EvaluationCycle::class, 'evaluation_cycle_id');
    }
}
