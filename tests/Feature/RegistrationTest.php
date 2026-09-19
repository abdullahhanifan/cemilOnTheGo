<?php

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
});

/**
 * @return array<string, string>
 */
function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'fname' => 'Jane',
        'lname' => 'Doe',
        'username' => 'janedoe',
        'email' => 'jane@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ], $overrides);
}

test('signup answers 404 when the registration flag is off', function () {
    config(['alh.registration_enabled' => false]);

    $this->get('/signup')->assertNotFound();
    $this->post('/signup', registrationPayload())->assertNotFound();

    $this->assertDatabaseMissing('users', ['username' => 'janedoe']);
});

test('signup creates an active user with the default role when the flag is on', function () {
    config(['alh.registration_enabled' => true]);

    $this->get('/signup')
        ->assertSuccessful()
        ->assertSee('Username');

    $this->post('/signup', registrationPayload())
        ->assertRedirect(route('signin'));

    $user = User::where('username', 'janedoe')->firstOrFail();

    expect($user->name)->toBe('Jane Doe')
        ->and($user->email)->toBe('jane@example.test')
        ->and($user->status)->toBe('active')
        ->and($user->hasRole(config('alh.default_role')))->toBeTrue()
        ->and($user->hasRole(config('alh.super_admin_role')))->toBeFalse();
});

test('registered user can sign in with the chosen username', function () {
    config(['alh.registration_enabled' => true]);

    $this->post('/signup', registrationPayload())->assertRedirect(route('signin'));

    Livewire\Livewire::test('pages::auth.signin')
        ->set('username', 'janedoe')
        ->set('password', 'password123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));
});

test('signup validates username and password confirmation', function () {
    config(['alh.registration_enabled' => true]);

    User::factory()->create(['username' => 'taken']);

    $this->post('/signup', registrationPayload(['username' => 'taken']))
        ->assertSessionHasErrors('username');

    $this->post('/signup', registrationPayload(['username' => 'bad name!']))
        ->assertSessionHasErrors('username');

    $this->post('/signup', registrationPayload(['username' => '']))
        ->assertSessionHasErrors('username');

    $this->post('/signup', registrationPayload(['password_confirmation' => 'different']))
        ->assertSessionHasErrors('password');

    expect(User::where('email', 'jane@example.test')->exists())->toBeFalse();
});

test('signup requires the captcha when captcha is enabled', function () {
    config(['alh.registration_enabled' => true, 'captcha.disable' => false]);

    $this->post('/signup', registrationPayload())
        ->assertSessionHasErrors('captcha');

    $this->post('/signup', registrationPayload(['captcha' => 'wrong']))
        ->assertSessionHasErrors('captcha');

    expect(User::where('username', 'janedoe')->exists())->toBeFalse();
});
