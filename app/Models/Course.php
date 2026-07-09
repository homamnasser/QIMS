<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Course extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'mosque_id',
        'project_id',
        'start_date',
        'end_date',
        'is_active',
        'parent_course_id',
        'supervisor_id',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function mosque()
    {
        return $this->belongsTo(Mosque::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function parentCourse()
    {
        return $this->belongsTo(Course::class, 'parent_course_id');
    }

    public function subCourse()
    {
        return $this->hasOne(Course::class, 'parent_course_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function sabrs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Sabr::class, 'course');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['name'] ?? null, function ($q, $name) {
            $q->where('name', 'like', '%' . $name . '%');
        })
            ->when($filters['mosque_id'] ?? null, function ($q, $mosqueId) {
                $q->where('mosque_id', $mosqueId);
            })
            ->when($filters['project_id'] ?? null, function ($q, $projectId) {
                $q->where('project_id', $projectId);
            })
            ->when($filters['mosque_name'] ?? null, function ($q, $mosqueName) {
                $q->whereHas('mosque', function ($q) use ($mosqueName) {
                    $q->where('name', 'like', '%' . $mosqueName . '%');
                });
            })
            ->when($filters['project_name'] ?? null, function ($q, $projectName) {
                $q->whereHas('project', function ($q) use ($projectName) {
                    $q->where('name', 'like', '%' . $projectName . '%');
                });
            });
    }
    public function exams(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Exam::class, 'course', 'id');
    }
    public function absences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentCourseAbsence::class, 'course', 'id');
    }
    public function studentsMarks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentMark::class, 'course', 'id');
    }
}
