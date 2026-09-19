<?php

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('guest can open signin and forgot-password', function () {
    $this->get('/signin')->assertOk();
    $this->get('/forgot-password')->assertOk();
});

test('guest is redirected from the dashboard to signin', function () {
    $this->get('/dashboard')->assertRedirect('/signin');
});

test('root redirects to the dashboard', function () {
    $this->get('/')->assertRedirect(route('dashboard'));
});

test('admin can open the dashboard and both RBAC pages', function () {
    $this->seed(RbacSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Welcome back, '.$admin->name);
    $this->actingAs($admin)->get('/rbac/users')->assertOk();
    $this->actingAs($admin)->get('/rbac/roles')->assertOk();
});

test('unknown URLs render the generic 404 page', function () {
    $this->get('/definitely/not/a/page')
        ->assertNotFound()
        ->assertSee('ERROR 404')
        ->assertSee(config('app.name'));
});

test('health endpoint answers 200', function () {
    $this->get('/health')->assertOk()->assertSee('OK');
});

test('pages carry the configured app name', function () {
    $this->get('/signin')
        ->assertOk()
        ->assertSee('<title>Log In | '.config('app.name').'</title>', false);
});
