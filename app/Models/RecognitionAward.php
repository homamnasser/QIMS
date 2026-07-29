<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecognitionAward extends Model
{
    protected $guarded = [];

    protected $casts = [
        'receives_material_gift' => 'boolean',
        'metadata' => 'array',
    ];

    public function result()
    {
        return $this->belongsTo(EvaluationResult::class, 'evaluation_result_id');
    }
}
