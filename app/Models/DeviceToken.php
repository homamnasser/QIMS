<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DeviceToken extends Model
{
    protected $fillable = ['tokenable_type', 'tokenable_id', 'token', 'device_name'];

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }
}
