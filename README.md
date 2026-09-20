# cemilOnTheGo

Internal jastip (personal shopping) management app: stores, partners (mitra) who buy goods at those stores, customer invoices, delivery schedules and partner payments.

Built on Laravel + Livewire, started from the ALHCore starter kit (login, roles & permissions, admin layout, User/Role management).

## Status

| Module | State |
|---|---|
| Access control (users, roles) | Done (from the starter kit) |
| **Toko** (stores) | Done: list, create, edit, deactivate |
| **Produk** (products) | Done: list, create, edit, deactivate, one optional photo |
| **Mitra** (partners) | Done: list, create, edit, deactivate |
| Invoices, deliveries, partner purchases and payments | Not started |

## Requirements

- PHP 8.3 with the `gd` extension (needed by the captcha; set `CAPTCHA_DISABLE=true` to turn the captcha off)
- MySQL (or SQLite for a quick start, which also needs `pdo_sqlite`)
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

1. Clone the repository, then `composer install`.
2. `cp .env.example .env` and `php artisan key:generate`.
3. Create an empty MySQL database and set the `DB_*` values in `.env`. Credentials belong in `.env` only, which is git-ignored; never put them in `.env.example` or any tracked file.
4. Set the `ADMIN_*` values in `.env` (name, username, email, password).
5. `php artisan migrate --seed`
6. `php artisan storage:link` (once, so product photos can be served)
7. `npm ci && npm run build`
8. `php artisan serve`, open `/signin`, and log in with `ADMIN_USERNAME` / `ADMIN_PASSWORD`.

For SQLite instead of MySQL: set `DB_CONNECTION=sqlite`, run `touch database/database.sqlite`, then continue from step 4 (skip the MySQL database in step 3).

**Change the admin password right after the first login.** `.env.example` ships a placeholder password on purpose. In production the seeder refuses to run when `ADMIN_PASSWORD` is empty, still the placeholder, or shorter than 12 characters.

## Configuration

| Variable | Default | Purpose |
|---|---|---|
| `ALH_REGISTRATION_ENABLED` | `false` | Public `/signup`. When off, the routes answer 404. New sign-ups get the neutral `user` role. |
| `CAPTCHA_DISABLE` | `false` | Captcha on sign in, sign up and forgot password. Needs `gd` when on. |
| `TRUSTED_PROXIES` | empty | Comma-separated proxy IPs/CIDRs to trust. Empty trusts none. |
| `ADMIN_NAME`, `ADMIN_USERNAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | see `.env.example` | Initial admin account created by `AdminUserSeeder`. |

## Roles and permissions

- `admin`: every permission (and bypasses permission checks).
- `user`: the neutral default role, with no permissions at all. A self-registered account gets it (public signup is off by default) and can do nothing until an admin grants access.
- `mitra`: seeded empty and unused. Partners are records in the `partners` table, not users; the role is reserved for a future partner login.

Permissions are derived from the menu in `app/Helpers/MenuHelper.php` as `group:resource-action`. Add a `permission_key` there and re-run `php artisan db:seed --class=RbacSeeder`.

## Toko (stores)

A store has a name, city, area (e.g. mall or market), address, contact (name and phone), opening hours, notes and a status.

- Permissions: `store:store-view`, `store:store-create`, `store:store-edit`, `store:store-delete`.
- Stores are never deleted. "Nonaktifkan" sets `status = inactive` and needs `store:store-delete`; the same permission is required to switch an active store to inactive from the edit form. Reactivating only needs `store:store-edit`.
- Opening hours are one or more rows of day range plus opening and closing time (e.g. Senin–Jumat 10.00–22.00). Days follow ISO-8601 (1 = Monday ... 7 = Sunday); a range cannot wrap around the week, so split it into two rows.
- Every action authorizes itself (route middleware, `mount()`, and each Livewire action), so hiding a button is never the only protection.

## Produk (products)

A product belongs to exactly one store and has a name, an optional variant, an optional store code (SKU), a unit (pcs, box, kg...), a buy price (what the store charges), a sell price (the default price to the customer), notes and a status of its own.

- Permissions: `product:product-view`, `product:product-create`, `product:product-edit`, `product:product-delete`. Store permissions grant nothing on products.
- There is no variants table: every variant (size, flavor...) is a separate product. A product is unique per store by name + variant.
- Prices are whole rupiah. **Estimasi margin** (sell price minus buy price) is calculated for display and never stored.
- Prices on a product are reference prices. Invoices will copy them per line and allow editing there; the price actually paid at the store is recorded on the partner purchase. Tax and service fees belong to the invoice, not the product.
- The store cannot be changed after creation, and only active stores can receive new products. Deactivating a store leaves its products as they are.
- "Nonaktifkan" follows the same rule as stores: it needs `product:product-delete`, also when done by switching the status in the edit form.
- The list shows when the buy price was last checked. It refreshes when the buy price changes or when the admin ticks "Harga beli sudah dicek ulang".

- **Photo:** one optional photo per product. Accepted: JPG, PNG and WebP, at most 2 MB and 6000 x 6000 pixels; GIF and SVG are refused (SVG can carry scripts). The type is judged by the file's content, not its name. The file is stored on the `public` disk under `products/` with a random 40-character name, so the client's file name never reaches the disk or the URL. Replacing or removing a photo deletes the old file, and a failed save leaves no new file behind. PHP's `upload_max_filesize` and `post_max_size` must be at least 2 MB (the PHP default of 2M is exactly enough).

See [docs/PROJECT_DIRECTION.md](docs/PROJECT_DIRECTION.md) for the decisions behind the modules (including that partner purchases are entered by the admin for now, because partner login is postponed).

## Mitra (partners)

A partner (mitra) is someone who buys goods at a store on request and is paid afterwards. Partners are records in their own `partners` table, not users. Purchases and payments will reference them through `partner_id`.

- Fields: name, phone, city, area, payment method (`bank` or `e_wallet`), payment provider (free text such as BCA or GoPay, optional), account name, account number, notes and a status. Name, phone and city are required. Payment method, account name and account number are all filled or all empty; a provider on its own is refused. The list shows the provider next to the method.
- Permissions: `partner:partner-view`, `partner:partner-create`, `partner:partner-edit`, `partner:partner-delete`. Other modules' permissions grant nothing here.
- Partners are never deleted. "Nonaktifkan" needs `partner:partner-delete`, also when the status is flipped in the edit form; reactivating needs only `partner:partner-edit`.
- **The account number is encrypted** with `APP_KEY`. In the list only its last four digits are shown; the full number is loaded only into the edit form (which needs `partner:partner-edit`). It is hidden from model serialization and can be neither searched nor sorted on. Keep `APP_KEY` safe: if it is lost or changed, stored numbers can no longer be read and have to be entered again (the page keeps working and says so).
- `partners.user_id` (nullable, unique) is reserved for a future partner login. Nothing reads or writes it yet.

See [docs/PROJECT_DIRECTION.md]
- `composer run dev`: server, queue listener and Vite
- `php artisan test`: Pest suite (in-memory SQLite, never touches your MySQL database)
- `vendor/bin/pint`: code style
