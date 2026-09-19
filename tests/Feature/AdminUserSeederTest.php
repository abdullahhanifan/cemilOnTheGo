<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config([
        'alh.admin' => [
            'name' => 'Test Admin',
            'username' => 'testadmin',
            'email' => 'testadmin@example.test',
            'password' => 'a-long-enough-password',
        ],
    ]);
});

test('the database seeder creates the admin with the admin role', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('username', 'testadmin')->firstOrFail();

    expect($admin->email)->toBe('testadmin@example.test')
        ->and($admin->status)->toBe('active')
        ->and($admin->hasRole(config('alh.super_admin_role')))->toBeTrue()
        ->and(password_verify('a-long-enough-password', $admin->password))->toBeTrue();
});

test('the admin seeder is idempotent', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(User::where('username', 'testadmin')->count())->toBe(1);
});

test('the admin seeder refuses to run in production with an unsafe password', function (?string $password) {
    $this->seed(RbacSeeder::class);
    config(['alh.admin.password' => $password]);
    $this->app['env'] = 'production';

    // Called directly: `db:seed` would ask for a confirmation prompt in production.
    (new AdminUserSeeder)->run();
})->with([
    'empty' => [''],
    'missing' => [null],
    'placeholder' => ['GANTI-SEBELUM-DIPAKAI'],
    'too short' => ['short-pass1'],
])->throws(RuntimeException::class, 'Refusing to seed the admin user in production');

test('the admin seeder runs in production with a strong password', function () {
    $this->seed(RbacSeeder::class);
    $this->app['env'] = 'production';

    (new AdminUserSeeder)->run();

    expect(User::where('username', 'testadmin')->exists())->toBeTrue();
});
