<?php

namespace App\Models;

use App\Traits\HasRoleFamilies;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Student extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoleFamilies, HasRoles, Notifiable;

    protected $guard_name = 'web';

    protected $fillable = [
        'mosque_id',
        'selfnumber',
        'first_name',
        'last_name',
        'username',
        'phone_number',
        'birth_date',
        'academic_class',
        'reading_level',
        'father_name',
        'parent_social_state',
        'father_phone',
        'password',
        'image',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'birth_date' => 'date',
    ];

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['first_name'] ?? null, function ($q, $firstName) {
            $q->where('first_name', 'like', '%'.$firstName.'%');
        })
            ->when($filters['last_name'] ?? null, function ($q, $lastName) {
                $q->where('last_name', 'like', '%'.$lastName.'%');
            })
            ->when($filters['academic_class'] ?? null, function ($q, $academicClass) {
                $q->where('academic_class', $academicClass);
            })
            ->when($filters['reading_level'] ?? null, function ($q, $readingLevel) {
                $q->where('reading_level', $readingLevel);
            })
            ->when($filters['parent_social_state'] ?? null, function ($q, $parentSocialState) {
                $q->where('parent_social_state', $parentSocialState);
            });
    }

    public function studentCircles(): HasMany
    {
        return $this->hasMany(StudentCircle::class, 'student');
    }

    public function mosque(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Mosque::class);
    }

    public function surveyResponses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function selfNumberReservations(): HasMany
    {
        return $this->hasMany(StudentSelfNumberReservation::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'student_id');
    }

    public function sabrs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Sabr::class, 'student');
    }

    public function memorizations()
    {
        return $this->hasMany(Memorization::class, 'student');
    }

    public function warnings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Warning::class, 'student');
    }

    public function exams(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Exam::class, 'student', 'id');
    }

    public function absences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentCourseAbsence::class, 'student', 'id');
    }

    public function marksRecords(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentMark::class, 'student', 'id');
    }

    public function evaluationCandidates(): HasMany
    {
        return $this->hasMany(EvaluationCandidate::class);
    }

    public function sabrPartAchievements(): HasMany
    {
        return $this->hasMany(SabrPartAchievement::class);
    }
}
