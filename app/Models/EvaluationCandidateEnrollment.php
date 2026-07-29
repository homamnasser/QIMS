<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationCandidateEnrollment extends Model
{
    protected $guarded = [];

    public function candidate()
    {
        return $this->belongsTo(EvaluationCandidate::class, 'evaluation_candidate_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function circle()
    {
        return $this->belongsTo(Circle::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
