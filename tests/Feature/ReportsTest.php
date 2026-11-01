<?php

use App\Models\ActivityUpdate;
use App\Models\User;
use Livewire\Livewire;

test('reports only include updates in the chosen date range', function () {
    $this->actingAs(User::factory()->create());

    ActivityUpdate::factory()->create(['activity_date' => today()->subDays(2), 'remark' => 'Inside the range']);
    ActivityUpdate::factory()->create(['activity_date' => today()->subDays(20), 'remark' => 'Outside the range']);

    Livewire::test('pages::reports')
        ->set('from', today()->subDays(7)->toDateString())
        ->set('to', today()->toDateString())
        ->assertSee('Inside the range')
        ->assertDontSee('Outside the range');
});

test('reports can be filtered by person', function () {
    $this->actingAs(User::factory()->create());
    $kwame = User::factory()->create();

    ActivityUpdate::factory()->create(['user_id' => $kwame->id, 'activity_date' => today(), 'remark' => 'Kwame update']);
    ActivityUpdate::factory()->create(['activity_date' => today(), 'remark' => 'Someone else']);

    Livewire::test('pages::reports')
        ->set('userId', (string) $kwame->id)
        ->assertSee('Kwame update')
        ->assertDontSee('Someone else');
});

test('reports can be exported as CSV', function () {
    $this->actingAs(User::factory()->create());
    ActivityUpdate::factory()->create(['activity_date' => today()]);

    Livewire::test('pages::reports')
        ->call('export')
        ->assertFileDownloaded();
});
