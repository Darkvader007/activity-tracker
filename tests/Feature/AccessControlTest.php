<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('support');
});

test('support staff cannot open the admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('support');

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('admins can open the admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)->get('/admin')->assertOk();
});

test('deactivated users cannot log in', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});
