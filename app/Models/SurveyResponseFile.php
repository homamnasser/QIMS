<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyResponseFile extends Model
{
    protected $fillable = [
        'response_id',
        'answer_id',
        'access_token',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function response()
    {
        return $this->belongsTo(SurveyResponse::class, 'response_id');
    }

    public function answer()
    {
        return $this->belongsTo(SurveyAnswer::class, 'answer_id');
    }
}
