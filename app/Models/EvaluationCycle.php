<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationCycle extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'data_cutoff_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function policy()
    {
        return $this->belongsTo(EvaluationPolicy::class, 'policy_id');
    }

    public function periods()
    {
        return $this->hasMany(EvaluationPeriod::class)->orderBy('sequence');
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'evaluation_cycle_courses')->withTimestamps();
    }

    public function candidates()
    {
        return $this->hasMany(EvaluationCandidate::class);
    }

    public function runs()
    {
        return $this->hasMany(EvaluationRun::class);
    }

    public function latestFinalRun()
    {
        return $this->hasOne(EvaluationRun::class)
            ->ofMany(
                ['sequence' => 'max'],
                fn ($query) => $query->where('is_preview', false)
            );
    }
}
