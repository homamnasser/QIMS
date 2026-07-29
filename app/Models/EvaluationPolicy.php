<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationPolicy extends Model
{
    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
        'approved_at' => 'datetime',
    ];

    public function cycles()
    {
        return $this->hasMany(EvaluationCycle::class, 'policy_id');
    }
}
