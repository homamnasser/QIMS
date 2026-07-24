<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyAnswer extends Model
{
    protected $fillable = [
        'response_id',
        'question_id',
        'question_key',
        'question_title',
        'question_type',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function response()
    {
        return $this->belongsTo(SurveyResponse::class, 'response_id');
    }

    public function question()
    {
        return $this->belongsTo(SurveyQuestion::class);
    }

    public function files()
    {
        return $this->hasMany(SurveyResponseFile::class, 'answer_id');
    }
}
