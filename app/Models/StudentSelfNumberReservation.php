<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSelfNumberReservation extends Model
{
    protected $table = 'student_selfnumber_reservations';

    protected $fillable = [
        'selfnumber',
        'student_id',
        'mosque_id',
        'assigned_at',
        'deactivated_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function mosque(): BelongsTo
    {
        return $this->belongsTo(Mosque::class);
    }
}
