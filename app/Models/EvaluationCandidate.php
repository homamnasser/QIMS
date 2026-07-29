<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationCandidate extends Model
{
    protected $guarded = [];

    public function cycle()
    {
        return $this->belongsTo(EvaluationCycle::class, 'evaluation_cycle_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function mosque()
    {
        return $this->belongsTo(Mosque::class);
    }

    public function enrollments()
    {
        return $this->hasMany(EvaluationCandidateEnrollment::class);
    }

    public function results()
    {
        return $this->hasMany(EvaluationResult::class);
    }
}
