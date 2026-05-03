<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CourseDateLesson extends Pivot
{
    use HasFactory;

    protected $table = 'course_date_lesson';
    public $incrementing = true;
}
