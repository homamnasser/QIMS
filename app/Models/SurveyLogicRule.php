<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyLogicRule extends Model
{
    protected $fillable = [
        'survey_id',
        'source_question_id',
        'target_type',
        'target_question_id',
        'target_section_id',
        'action',
        'operator',
        'values',
    ];

    protected $casts = [
        'values' => 'array',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    public function sourceQuestion()
    {
        return $this->belongsTo(SurveyQuestion::class, 'source_question_id');
    }

    public function targetQuestion()
    {
        return $this->belongsTo(SurveyQuestion::class, 'target_question_id');
    }

    public function targetSection()
    {
        return $this->belongsTo(SurveySection::class, 'target_section_id');
    }
}
