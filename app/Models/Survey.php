<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Survey extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'public_token',
        'name',
        'description',
        'logo_path',
        'status',
        'starts_at',
        'ends_at',
        'allow_multiple_responses',
        'settings',
        'created_by',
        'mosque_id',
        'published_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'published_at' => 'datetime',
        'allow_multiple_responses' => 'boolean',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Survey $survey): void {
            $survey->public_token ??= Str::random(48);
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mosque()
    {
        return $this->belongsTo(Mosque::class);
    }

    public function sections()
    {
        return $this->hasMany(SurveySection::class)->orderBy('display_order');
    }

    public function questions()
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('display_order');
    }

    public function studentFields()
    {
        return $this->hasMany(SurveyStudentField::class)->orderBy('display_order');
    }

    public function logicRules()
    {
        return $this->hasMany(SurveyLogicRule::class);
    }

    public function responses()
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function isAvailable(): bool
    {
        $now = now();

        return $this->status === self::STATUS_PUBLISHED
            && (! $this->starts_at || $this->starts_at->lte($now))
            && (! $this->ends_at || $this->ends_at->gte($now));
    }

    public function publicUrl(): string
    {
        return config('surveys.frontend_url').'/survey/'.$this->public_token;
    }

    public function previewUrl(): string
    {
        return config('surveys.frontend_url').'/surveys/'.$this->id.'/preview';
    }
}
