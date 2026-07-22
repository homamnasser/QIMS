<?php

namespace App\Models;

use App\Enums\RoleFamily;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'role_family',
        'is_system',
        'suggested_role_family',
        'role_family_reviewed_at',
    ];

    protected $casts = [
        'role_family' => RoleFamily::class,
        'is_system' => 'boolean',
        'suggested_role_family' => RoleFamily::class,
        'role_family_reviewed_at' => 'datetime',
    ];
}
