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

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['name'] ?? null, function ($q, $name) {
            $q->where('name', 'like', '%' . $name . '%');
        })
        ->when($filters['supervisor'] ?? null, function ($q, $supervisor) {
            $q->where('supervisor', $supervisor);
        })
        ->when($filters['audience'] ?? null, function ($q, $audience) {
            $q->where('audience', 'like', '%' . $audience . '%');
        })
        ->when(isset($filters['active']) && !is_null($filters['active']), function ($q) use ($filters) {
            $q->where('is_active', (bool) $filters['active']);
        });
    }

    public function supervisorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor', 'id');
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'project_id');
    }
}
