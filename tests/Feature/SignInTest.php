<?php

use App\Models\User;
use App\Overrides\Captcha;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

test('signin component renders successfully', function () {
    $this->get(route('signin'))
        ->assertSuccessful()
        ->assertSeeLivewire('pages::auth.signin');
});

test('can login with valid credentials', function () {
    $user = User::factory()->create([
        'username' => 'testuser',
        'password' => bcrypt('password123'),
    ]);

    Livewire::test('pages::auth.signin')
        ->set('username', 'testuser')
        ->set('password', 'password123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('cannot login with invalid credentials', function () {
    User::factory()->create([
        'username' => 'testuser',
        'password' => bcrypt('password123'),
    ]);

    Livewire::test('pages::auth.signin')
        ->set('username', 'testuser')
        ->set('password', 'wrongpassword')
        ->call('login')
        ->assertHasErrors(['username'])
        ->assertNoRedirect();

    $this->assertGuest();
});

test('validation works as expected', function () {
    Livewire::test('pages::auth.signin')
        ->set('username', '')
        ->set('password', '')
        ->call('login')
        ->assertHasErrors(['username' => 'required', 'password' => 'required']);
});

test('signin page links to forgot password', function () {
    $this->get(route('signin'))
        ->assertSuccessful()
        ->assertSee('Forgot password?')
        ->assertSee(route('password.request'), false);
});

test('signin rejects a wrong captcha when captcha is enabled', function () {
    config(['captcha.disable' => false]);

    User::factory()->create([
        'username' => 'testuser',
        'password' => bcrypt('password123'),
    ]);

    Livewire::test('pages::auth.signin')
        ->set('username', 'testuser')
        ->set('password', 'password123')
        ->set('captcha', 'wrong')
        ->call('login')
        ->assertHasErrors(['captcha'])
        ->assertDispatched('captcha-refresh');

    $this->assertGuest();
});

test('signin requires the captcha field when captcha is enabled', function () {
    config(['captcha.disable' => false]);

    Livewire::test('pages::auth.signin')
        ->set('username', 'testuser')
        ->set('password', 'password123')
        ->call('login')
        ->assertHasErrors(['captcha' => 'required']);
});

/**
 * Store a known captcha the same way Mews\Captcha\Captcha::generate() does.
 */
function issueCaptcha(string $code): void
{
    $hash = Hash::make($code);

    session()->put('captcha', ['sensitive' => true, 'key' => $hash, 'encrypt' => false]);
    Cache::put('captcha_'.md5($hash), $code, 60);
}

test('signin accepts a correct captcha when captcha is enabled', function () {
    config(['captcha.disable' => false]);

    $user = User::factory()->create([
        'username' => 'testuser',
        'password' => bcrypt('password123'),
    ]);

    issueCaptcha('Ab3xYz');

    Livewire::test('pages::auth.signin')
        ->set('username', 'testuser')
        ->set('password', 'password123')
        ->set('captcha', 'Ab3xYz')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('a captcha code is single use and is cleared after a failed attempt', function () {
    config(['captcha.disable' => false]);

    User::factory()->create([
        'username' => 'testuser',
        'password' => bcrypt('password123'),
    ]);

    issueCaptcha('Ab3xYz');

    Livewire::test('pages::auth.signin')
        ->set('username', 'testuser')
        ->set('password', 'wrong-password')
        ->set('captcha', 'Ab3xYz')
        ->call('login')
        ->assertHasErrors(['username'])
        ->assertSet('captcha', '')
        ->assertDispatched('captcha-refresh')
        // the consumed code no longer validates, even with the right password
        ->set('password', 'password123')
        ->set('captcha', 'Ab3xYz')
        ->call('login')
        ->assertHasErrors(['captcha']);

    $this->assertGuest();
});

test('the captcha image renders', function () {
    config(['captcha.disable' => false]);

    $response = captcha('flat');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('image/jpeg');
})->skip(! extension_loaded('gd'), 'The gd extension is required for the captcha.');

test('the captcha bound to the container is the app override', function () {
    expect(app('captcha'))->toBeInstanceOf(Captcha::class);
});

test('the server never renders the captcha image url', function (string $path) {
    config(['captcha.disable' => false, 'alh.registration_enabled' => true]);

    // Livewire loads any <img src> it parses on a re-render, which fires an extra request that
    // issues a second code and overwrites the one in the session. The src is set from JS only.
    $html = $this->get($path)->assertOk()->getContent();

    expect($html)->not->toMatch('/<img[^>]+captcha\/flat/')
        ->and($html)->toContain('x-init="refresh()"');
})->with(['/signin', '/forgot-password', '/signup']);

test('re-rendering the signin component does not render the captcha image url', function () {
    config(['captcha.disable' => false]);

    $html = Livewire::test('pages::auth.signin')
        ->set('username', 'nobody')
        ->call('login')
        ->html();

    expect($html)->not->toMatch('/<img[^>]+captcha\/flat/');
});

test('a captcha code stays valid for a few minutes', function () {
    config(['captcha.disable' => false]);

    $user = User::factory()->create([
        'username' => 'testuser',
        'password' => bcrypt('password123'),
    ]);

    captcha('flat');
    $code = implode('', (array) Cache::get('captcha_'.md5(session('captcha.key'))));

    $this->travel(3)->minutes();

    Livewire::test('pages::auth.signin')
        ->set('username', 'testuser')
        ->set('password', 'password123')
        ->set('captcha', $code)
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
})->skip(! extension_loaded('gd'), 'The gd extension is required for the captcha.');
