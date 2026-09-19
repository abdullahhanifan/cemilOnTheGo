<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>

<project-conventions>

## Identity

ALH Core — Laravel + Livewire starter kit (git template repository). Before changing code, read `SPEC_ALHCore_v0.1.md`; the companion documents (`SESSION-SUMMARY.md`, `DESIGN_SYSTEM.md`, `SECURITY_GUIDELINES.md`) are added in Tahap 3.

Colors and font are tokens in `@theme` (`resources/css/app.css`). Use the utilities (`bg-accent-primary`, `text-neutral-label`, `border-neutral-border`, `font-sans`) instead of hard-coded hex values. Accent (brand) and neutral (structure) tokens are separate on purpose and must not reference each other.

## New Pages: Livewire 4 SFC (Mandatory)

All new pages MUST be Livewire 4 single-file components. Do NOT create traditional Blade views with `view()` route closures.

**Creating:**
```bash
php artisan make:livewire pages::path.name --no-interaction
```
Example: `php artisan make:livewire pages::dashboard.analytics --no-interaction`

This creates `resources/views/pages/path/⚡name.blade.php`.

**Routing:** Use `Route::livewire()`, not `Route::get()` closures:
```php
Route::livewire('/path', 'pages::path.name')->name('name');
```
Authenticated routes:
```php
Route::middleware(['auth'])->group(function () {
    Route::livewire('/path', 'pages::path.name')->name('name');
});
```

**Attributes on every page component:**
```blade
<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Page Name')] class extends Component {
    //
};
?>
```

The layout `layouts/app.blade.php` renders both `@yield('content')` and `{{ $slot }}`, so SFC pages and classic Blade views work with it.

## Page Structure

Every page follows this structure inside the SFC `<div>` root:
```blade
<div>
    <div class="rounded-2xl border border-neutral-border bg-neutral-card p-5 xl:p-8">
        <!-- content here -->
    </div>
</div>
```
- Reuse existing `<x-*>` Blade components from `resources/views/components/` (`<x-ui.alert>`, `<x-form.input>`, `<x-form.captcha>`, ...) before writing a new one.
- Do NOT create Livewire state management for things Alpine.js already handles in the template (sidebar, modals, dropdowns). Alpine ships with Livewire; there is no separate `alpinejs` npm package.

## Sidebar Entries

New pages MUST add entries to `app/Helpers/MenuHelper.php` in `getMenuGroups()`:

```php
['icon' => 'user-management', 'name' => 'Users', 'path' => '/rbac/users', 'permission_key' => 'access-control:users']
```
The `icon` is the name of a file in `resources/views/components/svg/` (rendered with `<x-dynamic-component>`, so a missing file breaks the sidebar).

## RBAC Architecture — Single Source of Truth

`MenuHelper` is the **single source of truth** for both the sidebar menu and RBAC permissions. Do NOT hardcode permission lists anywhere else.

### How it works

```
MenuHelper::getMenuGroups()          ← SSOT: icon, name, path, permission_key
        │
        ├─► MenuHelper::getPermissionGroups()   ← used by ⚡roles.blade.php (form UI)
        │
        └─► MenuHelper::getAllPermissions()      ← used by RbacSeeder (seed DB)
```

### Permission naming convention

`{group}:{resource}-{action}` — e.g. `access-control:users-view`, `access-control:roles-delete`

Actions are always: `view`, `create`, `edit`, `delete`.

### Roles

`config('alh.super_admin_role')` (default `admin`) receives every permission and bypasses all checks through `Gate::before`. `config('alh.default_role')` (default `user`) only has `dashboard:dashboard-view`. Roles are created in `RbacSeeder`, never by hand.

### Adding a new menu page with permissions

**Only one file needs to change: `MenuHelper.php`.**

1. Add the item with a `permission_key` to `getMenuGroups()`.
2. Re-run the seeder to create the new permissions in DB:
   ```bash
   php artisan db:seed --class=RbacSeeder
   ```
3. The RBAC form in Role Management will automatically show the new permission group — no code changes needed there.

## Commands

| Task | Command |
|---|---|
| Dev server (all services) | `composer run dev` |
| Run all tests | `composer run test` |
| Run single test | `php artisan test --compact --filter=testName` |
| PHP formatting | `vendor/bin/pint --format agent` |
| Build frontend | `npm run build` |
| Clear all caches | `php artisan optimize:clear` |
| Create test | `php artisan make:test --pest SomeFeatureTest` |

## Testing

- Use `LazilyRefreshDatabase` trait (not `RefreshDatabase`)
- Tests auto-discovered in `tests/Feature/` and `tests/Unit/`
- Test settings live in `phpunit.xml` (there is no `.env.testing`); run `npm run build` once so the Vite manifest exists.

## Current State

- Livewire v4 single-file components live under `resources/views` (`pages/`, `components/`); `app/Livewire/` is not used.
- Auth is custom: `⚡signin` (Livewire, username based), controllers for signup and password reset.
- Not implemented yet (Tahap 2): permission middleware on routes, authorization inside Livewire component actions, login checks for `status`, rate limiting.
- PHP platform pinned to 8.3.0 in `composer.json`; use `composer run dev` for the full dev stack.

## RBAC Modularization Conventions

- **Modular Actions**: Edit and detail modals/actions for RBAC entities (e.g. Users) must be extracted into their own modular Livewire components.
- **Roles Action Limits**: Do NOT create a separate "Detail" modal/action for Roles; Roles only support "Edit" and "Delete" actions.

## Optimized SFC Page Component Pattern

Every SFC page component that displays a data table with modals MUST follow this pattern to avoid performance issues (delayed modal opening, redundant queries).

### PHP Section — Use `#[Computed]` for Data Queries

**NEVER** use manual `$xxxCache` properties or `getXxx()` methods called directly in templates. Use `#[Computed]` properties instead:

```php
<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Page Name')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function mount()
    {
        abort_unless(auth()->user()?->can('group:resource-view'), 403);
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    #[Computed]
    public function items()
    {
        return Model::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate($this->perPage);
    }

    #[On('item-saved')]
    public function refreshItems(): void
    {
        unset($this->items);
    }

    #[On('item-deleted')]
    public function refreshItemsAfterDelete(): void
    {
        unset($this->items);
    }
};
?>
```

### Template Section — Access Computed Property

```blade
@forelse($this->items as $item)
    <tr>
        <!-- columns -->
    </tr>
@empty
    <tr>
        <td colspan="5" class="...">No items found.</td>
    </tr>
@endforelse
```

For pagination, access the same computed property (cached automatically):

```blade
@php
    $paginator = $this->items;
@endphp
@if ($paginator->hasPages())
    <!-- pagination buttons -->
@endif
```

### Modal Pattern — Always Use Child Components

**NEVER** put modals inline in the parent page. Always use separate child Livewire components:

```blade
<!-- Parent page bottom -->
<livewire:module.item-form-modal />
<livewire:module.item-detail-modal />
<livewire:module.item-delete-modal />
```

Open modals via `$dispatch()` — this does NOT trigger a parent re-render:

```blade
<!-- Edit button -->
<button wire:click="$dispatch('open-item-form', { id: {{ $item->id }} })">
    <x-svg.pencil class="w-5 h-5" />
</button>

<!-- Delete button — pass display data to avoid extra query -->
<button wire:click="$dispatch('open-item-delete', { id: {{ $item->id }}, name: '{{ addslashes($item->name) }}' })">
    <x-svg.trash class="w-5 h-5" />
</button>
```

### Child Delete Modal Pattern

Pass display data (name, count) via dispatch parameters so the modal opens instantly without a database query:

```php
// resources/views/components/module/?item-delete-modal.blade.php
<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Item;

new class extends Component {
    public int $itemId = 0;
    public string $itemName = '';
    public bool $isDeleteOpen = false;

    #[On('open-item-delete')]
    public function openDelete(int $id, string $name): void
    {
        $this->itemId = $id;
        $this->itemName = $name;
        $this->isDeleteOpen = true;
    }

    public function deleteItem(): void
    {
        Item::findOrFail($this->itemId)->delete();
        $this->isDeleteOpen = false;
        $this->dispatch('item-deleted');
    }
};
?>
```

### Child Form Modal Pattern

For edit forms, load the model inside the child component (not the parent):

```php
<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Item;
use App\Models\Category;

new class extends Component {
    public int $itemId = 0;
    public string $name = '';
    public bool $isAddEditOpen = false;

    #[Computed]
    public function categories()
    {
        return Category::orderBy('name')->get();
    }

    #[On('open-item-form')]
    public function openForm(?int $id = null): void
    {
        $this->resetForm();
        if ($id) {
            $this->itemId = $id;
            $item = Item::findOrFail($id);
            $this->name = $item->name;
        }
        $this->isAddEditOpen = true;
    }

    public function saveItem(): void
    {
        // validate and save...
        $this->isAddEditOpen = false;
        $this->dispatch('item-saved');
    }

    private function resetForm(): void
    {
        $this->itemId = 0;
        $this->name = '';
        $this->resetValidation();
    }
};
?>
```

### Checklist for New Page Components

Before creating a new SFC page with data table + modals:

- [ ] Use `#[Computed]` for the main data query (not `getXxx()` method)
- [ ] Use `unset($this->xxx)` in `#[On]` event listeners to bust cache after mutations
- [ ] Template accesses `$this->xxx` (computed property), NOT `$this->getXxx()`
- [ ] All modals are separate child Livewire components (not inline)
- [ ] Delete modal receives display data via dispatch params (no extra query on open)
- [ ] Form modal loads model inside child component (not parent)
- [ ] Run `vendor/bin/pint --dirty --format agent` after changes

## Form Inputs

Build forms with `<x-form.input>` (and `<x-form.captcha>` for captcha) so labels, sizing, focus ring, and error states stay consistent, and style with the accent/neutral tokens.

## Livewire Performance Optimization (Mandatory)

These rules MUST be applied on every new Livewire page component that renders a data table. Skipping these causes measurable slowness in production.

### Rule 1 — `wire:key` is REQUIRED on every `@forelse` data table row

Every `<tr>` inside a `@forelse` loop that iterates over a Livewire computed property MUST have a `wire:key` with a **unique, model-scoped prefix + ID**. Without it, Livewire tears down and rebuilds the entire `<tbody>` DOM on every search keystroke or pagination change.

```blade
{{-- ❌ WRONG — Livewire cannot diff rows, re-renders entire table --}}
@forelse($this->items as $item)
    <tr class="...">

{{-- ✅ CORRECT — Livewire patches only changed rows --}}
@forelse($this->items as $item)
    <tr wire:key="item-{{ $item->id }}" class="...">
```

**Naming convention:** `wire:key="{model-slug}-{{ $model->id }}"` — e.g.:
- `wire:key="user-{{ $user->id }}"`
- `wire:key="role-{{ $role->id }}"`

Always use a prefix to avoid collisions if multiple loops exist on one page.

### Rule 2 — Session, Cache, and Queue drivers must use Redis in production

When configuring `.env` files or writing documentation, always specify:

```env
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

**Never** use `SESSION_DRIVER=database` or `CACHE_STORE=database` in production. This adds 50–200ms per Livewire request because every `wire:click` does a DB read + write for the session.

### Rule 3 — `php artisan optimize` is required after every production deploy

Always remind the user to run this after deploying PHP or config changes:

```bash
php artisan optimize     # caches config, routes, views, events
php artisan view:cache   # pre-compiles all Blade templates
```

### Rule 4 — Checklist addition for new data table pages

Append to the existing SFC page checklist:

- [ ] `wire:key="model-{{ $model->id }}"` on every `<tr>` inside `@forelse` loops

</project-conventions>
