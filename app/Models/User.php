<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\StaffWorkScope;
use App\Traits\HasRoleFamilies;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoleFamilies, HasRoles, Notifiable;

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
        'work_scope',
        'mosque_id',
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
        'work_scope' => StaffWorkScope::class,
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

    public function givenSabrs(): HasMany
    {
        return $this->hasMany(Sabr::class, 'giver');
    }

    public function givenMemorizations()
    {
        return $this->hasMany(Memorization::class, 'giver');
    }

    public function issuedWarnings(): HasMany
    {
        return $this->hasMany(Warning::class, 'warner');
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class, 'created_by');
    }

    public function mosque(): BelongsTo
    {
        return $this->belongsTo(Mosque::class);
    }

    public function isInstituteWide(): bool
    {
        return $this->work_scope !== StaffWorkScope::Mosque;
    }

    public function isMosqueScoped(): bool
    {
        return $this->work_scope === StaffWorkScope::Mosque;
    }

    public function canAccessMosque(int $mosqueId): bool
    {
        return $this->isInstituteWide()
            || (int) $this->mosque_id === $mosqueId;
    }

    /**
     * Return UI permissions after removing mutations that have institute-wide
     * consequences. The underlying Spatie permissions remain unchanged.
     *
     * @return Collection<int, string>
     */
    public function effectivePermissionNames()
    {
        $permissions = $this->getAllPermissions()->pluck('name');

        if (! $this->isMosqueScoped()) {
            return $permissions;
        }

        return $permissions->reject(fn (string $permission): bool => in_array(
            $permission,
            [
                'إنشاء دور',
                'عرض كافة الأدوار',
                'عرض تفاصيل الدور',
                'تعديل الدور',
                'حذف الدور',
                'عرض كافة الصلاحيات',
                'إنشاء مشروع',
                'تعديل المشروع',
                'حذف المشروع',
                'تعديل حالة المشروع',
                'إنشاء مسجد',
                'حذف مسجد',
            ],
            true
        ))->values();
    }

    public function canSupervise(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->getAllPermissions()
            ->contains('name', config('roles.capabilities.supervise'));
    }

    public function hasFullFieldOperationsAccess(): bool
    {
        return $this->getAllPermissions()->contains(
            'name',
            config('roles.capabilities.field_operations')
        );
    }
}
