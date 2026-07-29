<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecognitionBatch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function awards()
    {
        return $this->hasMany(RecognitionAward::class);
    }

    public function cycle()
    {
        return $this->belongsTo(EvaluationCycle::class, 'evaluation_cycle_id');
    }

    public function run()
    {
        return $this->belongsTo(EvaluationRun::class, 'evaluation_run_id');
    }
}
