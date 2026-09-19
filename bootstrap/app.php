<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureRegistrationEnabled;
use App\Http\Middleware\SetUserTimezone;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies as FrameworkTrustProxies;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(FrameworkTrustProxies::class, TrustProxies::class);
        $middleware->redirectUsersTo('/dashboard');
        $middleware->redirectGuestsTo('/signin');
        $middleware->web(append: [
            SetUserTimezone::class,
            CheckMaintenanceMode::class,
        ]);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'registration.enabled' => EnsureRegistrationEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Laravel's default handling already renders resources/views/errors/{404,500,503}.blade.php.
        // Do not catch every Throwable here: that turned auth redirects, validation errors,
        // 403 and 419 into HTTP 500 pages when APP_DEBUG=false.
    })->create();
