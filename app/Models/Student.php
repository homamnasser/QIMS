<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    protected $guard_name = 'web';
    protected $fillable = [
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
            $q->where('first_name', 'like', '%' . $firstName . '%');
        })
            ->when($filters['last_name'] ?? null, function ($q, $lastName) {
                $q->where('last_name', 'like', '%' . $lastName . '%');
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
}
