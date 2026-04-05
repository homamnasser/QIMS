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


    public function supervisorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor', 'id');
    }
}
