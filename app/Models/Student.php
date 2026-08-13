<?php

namespace App\Models;

use App\Traits\HasRoleFamilies;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
            })
            // بحث موحّد على الاسم والرقم الذاتي: حقل واحد في مساحة عمل الحلقة
            // بدل ثلاثة حقول منفصلة.
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(function ($search) use ($term) {
                    $search->where('first_name', 'like', '%'.$term.'%')
                        ->orWhere('last_name', 'like', '%'.$term.'%')
                        ->orWhere('selfnumber', 'like', '%'.$term.'%')
                        ->orWhere('username', 'like', '%'.$term.'%');
                });
            })
            ->when($filters['circle_id'] ?? null, function ($q, $circleId) {
                $q->whereHas('studentCircles', function ($enrollment) use ($circleId) {
                    $enrollment->where('circle', $circleId);
                });
            })
            // طلاب بلا حلقة طابور عمل حقيقي: لا رقم ذاتي لهم ولا يظهرون في أي سياق حلقة.
            // نستخدم isset لا ?? لأن '0' قيمة مقصودة.
            ->when(isset($filters['has_circle']), function ($q) use ($filters) {
                filter_var($filters['has_circle'], FILTER_VALIDATE_BOOLEAN)
                    ? $q->has('studentCircles')
                    : $q->doesntHave('studentCircles');
            });
    }

    public function studentCircles(): HasMany
    {
        return $this->hasMany(StudentCircle::class, 'student');
    }

    public function mosque(): BelongsTo
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

    public function sabrs(): HasMany
    {
        return $this->hasMany(Sabr::class, 'student');
    }

    public function memorizations()
    {
        return $this->hasMany(Memorization::class, 'student');
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(Warning::class, 'student');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'student', 'id');
    }

    public function readingImprovements(): HasMany
    {
        return $this->hasMany(ReadingImprovement::class, 'student');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(StudentCourseAbsence::class, 'student', 'id');
    }

    public function marksRecords(): HasMany
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
