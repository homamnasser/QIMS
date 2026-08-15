<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentNotification extends Model
{
    protected $fillable = ['student_id', 'title', 'body', 'route', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];
}
