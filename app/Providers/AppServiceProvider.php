<?php

namespace App\Providers;

use App\Overrides\Captcha;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $binding = function ($app) {
            return new Captcha(
                $app['Illuminate\Filesystem\Filesystem'],
                $app['Illuminate\Contracts\Config\Repository'],
                $app['Intervention\Image\ImageManager'],
                $app['Illuminate\Session\Store'],
                $app['Illuminate\Hashing\BcryptHasher'],
                $app['Illuminate\Support\Str']
            );
        };

        $this->app->bind('captcha', $binding);
        $this->app->bind(\Mews\Captcha\Captcha::class, $binding);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Implicitly grant the super admin role all permissions
        Gate::before(function ($user, $ability) {
            return $user->hasRole(config('alh.super_admin_role')) ? true : null;
        });
    }
}
