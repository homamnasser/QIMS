<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCircle extends Model
{
    use HasFactory;


    protected $fillable = [
        'student',
        'circle'
    ];

    public function studentDetails(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student');
    }

    public function circleDetails(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle');
    }
}
