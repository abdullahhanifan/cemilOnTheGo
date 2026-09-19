<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/proxy-probe', fn () => request()->isSecure() ? 'secure' : 'plain');
});

test('forwarded headers are ignored when no proxy is trusted', function () {
    config(['alh.trusted_proxies' => '']);

    $this->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('/proxy-probe')
        ->assertSee('plain');
});

test('forwarded headers are honoured for a proxy listed in TRUSTED_PROXIES', function () {
    config(['alh.trusted_proxies' => '127.0.0.1']);

    $this->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('/proxy-probe')
        ->assertSee('secure');
});
