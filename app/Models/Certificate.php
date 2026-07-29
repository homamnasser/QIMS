<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data_snapshot' => 'array',
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function result()
    {
        return $this->belongsTo(EvaluationResult::class, 'evaluation_result_id');
    }
}
