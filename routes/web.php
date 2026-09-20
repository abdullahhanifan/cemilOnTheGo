<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

// Auth check home page redirect
Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth'])->group(function () {
    // Dashboard landing page
    Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard');

    // Store pages
    Route::livewire('/store', 'pages::store.index')
        ->name('store.index')
        ->middleware('permission:store:store-view');

    // Product pages
    Route::livewire('/product', 'pages::product.index')
        ->name('product.index')
        ->middleware('permission:product:product-view');

    // Partner pages
    Route::livewire('/partner', 'pages::partner.index')
        ->name('partner.index')
        ->middleware('permission:partner:partner-view');

    // RBAC pages
    Route::livewire('/rbac/users', 'pages::rbac.users')
        ->name('rbac.users')
        ->middleware('permission:access-control:users-view');
    Route::livewire('/rbac/roles', 'pages::rbac.roles')
        ->name('rbac.roles')
        ->middleware('permission:access-control:roles-view');
});

// authentication pages
Route::livewire('/signin', 'pages::auth.signin')->name('signin');

// Public signup, disabled unless ALH_REGISTRATION_ENABLED=true
Route::middleware('registration.enabled')->group(function () {
    Route::get('/signup', [AuthController::class, 'showRegistrationForm'])->name('signup');
    Route::post('/signup', [AuthController::class, 'register'])->name('signup.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

Route::get('/reset-password', function () {
    return redirect()->route('password.request');
})->name('reset-password');

// Health check endpoint
Route::get('/health', function () {
    return response('OK', 200)->header('Content-Type', 'text/plain');
})->name('health');

// Maintenance preview
Route::get('/maintenance', function () {
    return response(view('pages.maintenance', ['title' => 'Maintenance']), 503);
})->name('maintenance');

Route::fallback(function () {
    abort(404);
});
