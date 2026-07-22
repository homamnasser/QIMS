<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Project;
use Illuminate\Support\Facades\App;
use App\Models\Circle;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\HasRoleFamilies;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasRoleFamilies;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'birth_date',
        'email',
        'password',
        'image',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'supervisor', 'id');
    }
    public function supervisedProject(): HasOne
    {
        return $this->hasOne(Project::class, 'supervisor', 'id');
    }

    public function circles(): HasMany
    {
        return $this->hasMany(Circle::class, 'teacher_id');
    }
    public function supervisedCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'supervisor_id');
    }
    public function writtenNotes()
    {
        return $this->hasMany(Note::class, 'user_id');
    }
    public function givenSabrs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Sabr::class, 'giver');
    }
    public function givenMemorizations()
    {
        return $this->hasMany(Memorization::class, 'giver');
    }
    public function issuedWarnings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Warning::class, 'warner');
    }

    public function canSupervise(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->getAllPermissions()
            ->contains('name', config('roles.capabilities.supervise'));
    }
}
