<?php

namespace App\Actions;
use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityUpdate;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RecordActivityUpdate
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
    public function handle(Activity $activity, User $user, ActivityStatus $status, ?string $remark = null): ActivityUpdate
    {
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'remark' => 'Your account is inactive and cannot record updates.',
            ]);
        }

        if (! $activity->is_active) {
            throw ValidationException::withMessages([
                'remark' => 'This activity has been retired and can no longer be updated.',
            ]);
        }

        return $activity->updates()->create([
            'user_id' => $user->id,
            'status' => $status,
            'remark' => filled($remark) ? trim($remark) : null,
            'activity_date' => today(),
        ]);
    }
}
