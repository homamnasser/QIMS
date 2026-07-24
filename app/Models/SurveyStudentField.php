<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyStudentField extends Model
{
    protected $fillable = [
        'survey_id',
        'field_key',
        'label',
        'mode',
        'is_required',
        'display_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }
}
