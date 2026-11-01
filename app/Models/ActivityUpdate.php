<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use Carbon\CarbonInterface;
use Database\Factories\ActivityUpdateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $activity_id
 * @property int $user_id
 * @property ActivityStatus $status
 * @property string|null $remark
 * @property CarbonInterface $activity_date
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class ActivityUpdate extends Model
{
    /** @use HasFactory<ActivityUpdateFactory> */
    use HasFactory;

    protected $fillable = [
        'activity_id',
        'user_id',
        'status',
        'remark',
        'activity_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => ActivityStatus::class,
            'activity_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Activity updates cannot be changed once recorded.'));
        static::deleting(fn () => throw new \LogicException('Activity updates cannot be deleted.'));
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
