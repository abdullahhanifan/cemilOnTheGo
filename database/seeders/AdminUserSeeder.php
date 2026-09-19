<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * The password placeholder shipped in .env.example.
     */
    private const PLACEHOLDER_PASSWORD = 'GANTI-SEBELUM-DIPAKAI';

    private const MIN_PRODUCTION_PASSWORD_LENGTH = 12;

    /**
     * Create the initial admin account from config('alh.admin').
     *
     * In production this refuses to run with an empty, placeholder, or short password.
     */
    public function run(): void
    {
        $admin = config('alh.admin');

        if (app()->isProduction()) {
            $this->ensureSafeProductionPassword((string) ($admin['password'] ?? ''));
        }

        if (blank($admin['email']) || blank($admin['password'])) {
            throw new RuntimeException('ADMIN_EMAIL and ADMIN_PASSWORD must be set in .env before seeding the admin user.');
        }

        // firstOrCreate keeps re-runs idempotent and never overwrites an existing password.
        $user = User::firstOrCreate(
            ['username' => $admin['username']],
            [
                'name' => $admin['name'],
                'email' => $admin['email'],
                'password' => $admin['password'],
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        $user->syncRoles([config('alh.super_admin_role')]);
    }

    private function ensureSafeProductionPassword(string $password): void
    {
        if ($password === '' || $password === self::PLACEHOLDER_PASSWORD || strlen($password) < self::MIN_PRODUCTION_PASSWORD_LENGTH) {
            throw new RuntimeException(
                'Refusing to seed the admin user in production: set ADMIN_PASSWORD in .env to a unique value of at least '
                .self::MIN_PRODUCTION_PASSWORD_LENGTH.' characters (not the .env.example placeholder).'
            );
        }
    }
}
