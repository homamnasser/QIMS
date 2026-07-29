<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationAuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];
}
