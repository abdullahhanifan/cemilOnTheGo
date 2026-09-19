<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(LazilyRefreshDatabase::class);

test('app responds normally when maintenance mode is false', function () {
    config(['app.is_maintenance' => false]);

    $response = $this->get('/signin');
    $response->assertStatus(200);
});

test('app returns 503 maintenance page when is_maintenance is true', function () {
    config(['app.is_maintenance' => true]);

    $response = $this->get('/signin');
    $response->assertStatus(503);
    $response->assertSee('MAINTENANCE');
});

test('health endpoints bypass maintenance mode', function () {
    config(['app.is_maintenance' => true]);

    $response = $this->get('/health');
    $response->assertStatus(200);
});

test('error pages render successfully', function () {
    config(['app.is_maintenance' => false]);

    $this->get('/maintenance')->assertStatus(503)->assertSee('MAINTENANCE');
    $this->get('/this-page-does-not-exist')->assertStatus(404)->assertSee('ERROR 404');
});

test('demo error routes are not registered', function () {
    config(['app.is_maintenance' => false]);

    $this->get('/error-404')->assertStatus(404);
    $this->get('/error-500')->assertStatus(404);
    $this->get('/error-503')->assertStatus(404);
});

test('unhandled exceptions render error-500 page when debug is false', function () {
    config([
        'app.is_maintenance' => false,
        'app.debug' => false,
    ]);

    Route::get('/test-server-error', function () {
        throw new Exception('Test server crash');
    });

    $response = $this->get('/test-server-error');
    $response->assertStatus(500);
    $response->assertSee('ERROR 500');
});
