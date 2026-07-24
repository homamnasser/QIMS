<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SurveyResponse extends Model
{
    protected $fillable = [
        'response_token',
        'survey_id',
        'student_id',
        'student_selfnumber_snapshot',
        'survey_snapshot',
        'student_data_snapshot',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'survey_snapshot' => 'array',
        'student_data_snapshot' => 'array',
        'submitted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SurveyResponse $response): void {
            $response->response_token ??= (string) Str::uuid();
        });
    }

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function answers()
    {
        return $this->hasMany(SurveyAnswer::class, 'response_id');
    }

    public function files()
    {
        return $this->hasMany(SurveyResponseFile::class, 'response_id');
    }
}
