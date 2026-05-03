<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Course;
use App\Models\Lesson;

class CourseDate extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'session_date'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons()
{
    return $this->belongsToMany(Lesson::class, 'course_date_lesson')
                ->using(CourseDateLesson::class)
                ->withTimestamps();
}
}
