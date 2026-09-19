<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;

/*
| Production behaviour: APP_DEBUG=false. A catch-all render callback used to turn every
| exception below into an HTTP 500 page.
*/

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config(['app.debug' => false]);
});

test('a guest opening a protected page is redirected to signin, not shown a 500', function () {
    $this->get('/dashboard')->assertRedirect('/signin');
});

test('a failed signup validation redirects back with errors, not a 500', function () {
    config(['alh.registration_enabled' => true]);

    $this->from('/signup')
        ->post('/signup', ['fname' => ''])
        ->assertRedirect('/signup')
        ->assertSessionHasErrors('fname');
});

test('http errors keep their own status code', function (int $status) {
    Route::get("/probe-{$status}", fn () => abort($status));

    $this->get("/probe-{$status}")->assertStatus($status);
})->with([403, 419, 429]);

test('unknown URLs render the 404 page', function () {
    $this->get('/definitely/not/a/page')->assertNotFound()->assertSee('ERROR 404');
});

test('unhandled exceptions render the 500 page', function () {
    Route::get('/probe-crash', fn () => throw new RuntimeException('boom'));

    $this->get('/probe-crash')->assertStatus(500)->assertSee('ERROR 500')->assertDontSee('boom');
});
