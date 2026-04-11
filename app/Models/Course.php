<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'name', 'description', 'mosque_id',
        'project_id', 'start_date', 'end_date', 'is_active', 'parent_course_id'
    ];
    protected $casts = [
    'is_active' => 'boolean',
    'start_date' => 'date',
    'end_date' => 'date',
];

    public function mosque() {
        return $this->belongsTo(Mosque::class);
    }

    public function project() {
        return $this->belongsTo(Project::class);
    }

    public function parentCourse() {
        return $this->belongsTo(Course::class, 'parent_course_id');
    }

   public function subCourse()
    {
        return $this->hasOne(Course::class, 'parent_course_id');
    }
}
