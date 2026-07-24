<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SurveyQuestion extends Model
{
    protected $fillable = [
        'question_key',
        'survey_id',
        'section_id',
        'type',
        'title',
        'description',
        'is_required',
        'display_order',
        'validation_rules',
        'settings',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'validation_rules' => 'array',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (SurveyQuestion $question): void {
            $question->question_key ??= (string) Str::uuid();
        });
    }

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    public function section()
    {
        return $this->belongsTo(SurveySection::class, 'section_id');
    }

    public function options()
    {
        return $this->hasMany(SurveyQuestionOption::class, 'question_id')->orderBy('display_order');
    }
}
