<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseDate extends Model
{
    use HasFactory;
    
    protected $fillable = ['course_id', 'session_date'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
