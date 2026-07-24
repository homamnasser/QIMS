<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mosque extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'mosque_code',
    ];

    protected $casts = [
        'next_student_sequence' => 'integer',
    ];

    public function courses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function students(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function selfNumberReservations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentSelfNumberReservation::class);
    }
}
