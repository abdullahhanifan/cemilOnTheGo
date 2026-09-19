# ALH Core

Starter kit Laravel + Livewire: login, role & permission, admin layout (sidebar + header), and User/Role management, ready to build a business module on top of.

> Draft README for Tahap 1. The final version is written in Tahap 3.

## Requirements

- PHP 8.3 with the `gd` extension (needed by the captcha; set `CAPTCHA_DISABLE=true` to turn the captcha off) and `pdo_sqlite` (default database)
- Composer 2
- Node.js 20.19+ (or 22.12+) and npm

## Stack

| Package | Version |
|---|---|
| PHP | 8.3 |
| Laravel | 13 |
| Livewire (single-file components, `⚡` prefix, under `resources/views`) | 4 |
| Spatie Laravel-Permission | 8 |
| mews/captcha | 3 |
| Tailwind CSS + Vite | 4 / 7 |
| Pest | 4 |

## Getting started

1. Click **Use this template** on GitHub, then clone your new repository.
2. `composer install`
3. `cp .env.example .env`
4. `php artisan key:generate`
5. Edit `.env` and set the `ADMIN_*` values (name, username, email, password).
6. `touch database/database.sqlite` (only for the default SQLite setup), then `php artisan migrate --seed`
7. `npm ci && npm run build`
8. `php artisan serve`, open `/signin`, and log in with `ADMIN_USERNAME` / `ADMIN_PASSWORD`.

**Change the admin password right after the first login.** `.env.example` ships a placeholder password on purpose. In production the seeder refuses to run when `ADMIN_PASSWORD` is empty, still the placeholder, or shorter than 12 characters.

## Configuration

| Variable | Default | Purpose |
|---|---|---|
| `ALH_REGISTRATION_ENABLED` | `false` | Public `/signup`. When off, the routes answer 404. |
| `CAPTCHA_DISABLE` | `false` | Captcha on sign in, sign up and forgot password. Needs `gd` when on. |
| `TRUSTED_PROXIES` | empty | Comma-separated proxy IPs/CIDRs to trust. Empty trusts none. |
| `ADMIN_NAME`, `ADMIN_USERNAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | see `.env.example` | Initial admin account created by `AdminUserSeeder`. |

Roles are `admin` (all permissions) and `user` (dashboard only). Permissions are derived from the menu in `app/Helpers/MenuHelper.php`; add a `permission_key` there and re-run `php artisan db:seed --class=RbacSeeder`.

## Development

- `composer run dev`: server, queue listener and Vite
- `php artisan test`: Pest suite (in-memory SQLite)
- `vendor/bin/pint`: code style
