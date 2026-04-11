<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'description',
        'audience',
        'logo',
        'supervisor',
        'is_active',
    ];
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function supervisorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor', 'id');
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'project_id');
    }
}
