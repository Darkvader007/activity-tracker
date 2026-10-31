<?php

namespace App\Models;

use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'category',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ActivityUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(ActivityUpdate::class);
    }

    /** @return HasOne<ActivityUpdate, $this> */
    public function latestUpdate(): HasOne
    {
        return $this->hasOne(ActivityUpdate::class)->latestOfMany();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<Activity> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public const CATEGORIES = [
        'SMS' => 'SMS',
        'USSD' => 'USSD',
        'Payments' => 'Payments',
        'Infrastructure' => 'Infrastructure',
        'Monitoring' => 'Monitoring',
        'Support' => 'Support',
    ];
}
