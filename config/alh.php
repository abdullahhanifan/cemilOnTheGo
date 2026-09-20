<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public Registration
    |--------------------------------------------------------------------------
    |
    | The /signup routes stay registered but answer 404 unless this flag is on.
    |
    */

    'registration_enabled' => env('ALH_REGISTRATION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    |
    | The super admin role bypasses every permission check (see Gate::before in
    | AppServiceProvider). The default role is neutral, with no permissions at all,
    | and is assigned to self-registered users (public signup is off by default).
    |
    */

    'super_admin_role' => 'admin',

    'default_role' => 'user',

    /*
    |--------------------------------------------------------------------------
    | Initial Admin Account
    |--------------------------------------------------------------------------
    |
    | Read by AdminUserSeeder. In production the seeder refuses to run with an
    | empty, placeholder, or short (< 12 characters) password.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'username' => env('ADMIN_USERNAME', 'admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated proxy IPs/CIDRs from env TRUSTED_PROXIES. Empty (the
    | default) trusts no proxy. Read by App\Http\Middleware\TrustProxies.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

];
