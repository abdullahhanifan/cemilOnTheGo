<?php

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

/*
| Characterization tests: they pin the current behaviour so Tahap 2 (RBAC hardening) has a
| baseline. Both gaps below (login status check, component-level authorization) were fixed in
| Tahap 2, so these are no longer marked todo() -- they now assert the correct behaviour.
*/

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
});

test('a user with the default role is blocked when opening /rbac/users directly', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    $this->actingAs($user)->get('/rbac/users')->assertForbidden();
});

test('an inactive user cannot sign in', function () {
    User::factory()->create([
        'username' => 'sleeper',
        'password' => bcrypt('password123'),
        'status' => 'inactive',
    ]);

    Livewire::test('pages::auth.signin')
        ->set('username', 'sleeper')
        ->set('password', 'password123')
        ->call('login')
        ->assertHasErrors(['username']);

    $this->assertGuest();
});

test('a user with the default role cannot create users through the user form modal', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    Livewire::actingAs($user)
        ->test('rbac.user-form-modal')
        ->call('openForm')
        ->set('name', 'Intruder')
        ->set('username', 'intruder')
        ->set('email', 'intruder@example.test')
        ->set('roleName', config('alh.super_admin_role'))
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('saveUser')
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['username' => 'intruder']);
});
