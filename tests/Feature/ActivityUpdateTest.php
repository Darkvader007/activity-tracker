<?php

use App\Actions\RecordActivityUpdate;
use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityUpdate;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('an update is recorded for today with the person who made it', function () {
    $user = User::factory()->create();
    $activity = Activity::factory()->create();

    $update = app(RecordActivityUpdate::class)->handle($activity, $user, ActivityStatus::Pending, '  Waiting on logs  ');

    expect($update->user_id)->toBe($user->id)
        ->and($update->status)->toBe(ActivityStatus::Pending)
        ->and($update->remark)->toBe('Waiting on logs')
        ->and($update->activity_date->isToday())->toBeTrue();
});

test('retired activities cannot be updated', function () {
    $activity = Activity::factory()->create(['is_active' => false]);

    app(RecordActivityUpdate::class)->handle($activity, User::factory()->create(), ActivityStatus::Done);
})->throws(ValidationException::class);

test('inactive users cannot record updates', function () {
    $user = User::factory()->create(['is_active' => false]);

    app(RecordActivityUpdate::class)->handle(Activity::factory()->create(), $user, ActivityStatus::Done);
})->throws(ValidationException::class);

test('recorded updates cannot be edited', function () {
    ActivityUpdate::factory()->create()->update(['remark' => 'changed']);
})->throws(LogicException::class);

test('recorded updates cannot be deleted', function () {
    ActivityUpdate::factory()->create()->delete();
})->throws(LogicException::class);
