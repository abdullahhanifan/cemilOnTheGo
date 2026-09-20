<?php

use App\Models\Store;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
});

/**
 * A user whose only role carries exactly the given permissions.
 *
 * @param  list<string>  $permissions
 */
function storeUserWith(array $permissions): User
{
    $role = Role::create(['name' => 'store-test-'.uniqid()]);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function storeAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    return $admin;
}

/**
 * The attribute that marks a store's row in the list table.
 *
 * Rows are asserted through it rather than through their text: the page also
 * renders the form modal and the filter dropdowns, whose placeholders and city
 * options can contain the same words as a store and would satisfy assertSee().
 */
function storeRow(Store $store): string
{
    return 'wire:key="store-'.$store->id.'"';
}

/*
|--------------------------------------------------------------------------
| Page access
|--------------------------------------------------------------------------
*/

test('guest is redirected to signin', function () {
    $this->get(route('store.index'))->assertRedirect(route('signin'));
});

test('user without store:store-view permission gets 403', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    $this->actingAs($user)
        ->get(route('store.index'))
        ->assertForbidden();
});

test('admin can view the store page', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    $this->actingAs($admin)
        ->get(route('store.index'))
        ->assertSuccessful()
        ->assertSeeLivewire('pages::store.index');
});

test('a role with only store:store-view can open the page', function () {
    $this->actingAs(storeUserWith(['store:store-view']))
        ->get(route('store.index'))
        ->assertSuccessful();
});

test('the store route is protected by permission middleware, not only by the component', function () {
    $middleware = Route::getRoutes()->getByName('store.index')->gatherMiddleware();

    expect($middleware)->toContain('permission:store:store-view');
});

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/

test('admin can create a store through the form modal', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    $this->actingAs($admin);

    Livewire::test('store.store-form-modal')
        ->call('openForm')
        ->set('name', 'Toko Sumber Makmur')
        ->set('city', 'Bandung')
        ->set('status', 'active')
        ->call('saveStore')
        ->assertHasNoErrors();

    expect(Store::where('name', 'Toko Sumber Makmur')->exists())->toBeTrue();
});

test('a store is saved with every field, including opening hours', function () {
    $this->actingAs(storeAdmin());

    Livewire::test('store.store-form-modal')
        ->call('openForm')
        ->set('name', 'Kue Mekar')
        ->set('city', 'Bandung')
        ->set('area', 'Paris Van Java')
        ->set('address', 'Jl. Sukajadi No. 137')
        ->set('contact_name', 'Budi Santoso')
        ->set('contact_phone', '081234567890')
        ->set('notes', 'Antre panjang di akhir pekan')
        ->set('status', 'active')
        ->call('addSchedule')
        // Strings, as a browser sends them from <select> and <input type="time">.
        ->set('schedules.0.day_from', '1')
        ->set('schedules.0.day_to', '5')
        ->set('schedules.0.opens_at', '10:00')
        ->set('schedules.0.closes_at', '21:00')
        ->call('addSchedule')
        ->set('schedules.1.day_from', '6')
        ->set('schedules.1.day_to', '7')
        ->set('schedules.1.opens_at', '09:00')
        ->set('schedules.1.closes_at', '22:00')
        ->call('saveStore')
        ->assertHasNoErrors()
        ->assertSet('isAddEditOpen', false);

    $store = Store::where('name', 'Kue Mekar')->firstOrFail();

    expect($store->city)->toBe('Bandung')
        ->and($store->area)->toBe('Paris Van Java')
        ->and($store->address)->toBe('Jl. Sukajadi No. 137')
        ->and($store->contact_name)->toBe('Budi Santoso')
        ->and($store->contact_phone)->toBe('081234567890')
        ->and($store->notes)->toBe('Antre panjang di akhir pekan')
        ->and($store->status)->toBe('active')
        ->and($store->opening_hours)->toBe([
            ['day_from' => 1, 'day_to' => 5, 'opens_at' => '10:00', 'closes_at' => '21:00'],
            ['day_from' => 6, 'day_to' => 7, 'opens_at' => '09:00', 'closes_at' => '22:00'],
        ]);
});

test('optional fields left blank are stored as null', function () {
    $this->actingAs(storeAdmin());

    Livewire::test('store.store-form-modal')
        ->call('openForm')
        ->set('name', 'Toko Minimal')
        ->set('city', 'Surabaya')
        ->call('saveStore')
        ->assertHasNoErrors();

    $store = Store::where('name', 'Toko Minimal')->firstOrFail();

    expect($store->area)->toBeNull()
        ->and($store->address)->toBeNull()
        ->and($store->contact_name)->toBeNull()
        ->and($store->contact_phone)->toBeNull()
        ->and($store->notes)->toBeNull()
        ->and($store->opening_hours)->toBeNull()
        ->and($store->status)->toBe('active');
});

test('name and city are required', function () {
    $this->actingAs(storeAdmin());

    Livewire::test('store.store-form-modal')
        ->call('openForm')
        ->call('saveStore')
        ->assertHasErrors(['name' => 'required', 'city' => 'required']);

    expect(Store::count())->toBe(0);
});

test('status must be active or inactive', function () {
    $this->actingAs(storeAdmin());

    Livewire::test('store.store-form-modal')
        ->call('openForm')
        ->set('name', 'Toko Status')
        ->set('city', 'Bandung')
        ->set('status', 'banned')
        ->call('saveStore')
        ->assertHasErrors(['status' => 'in']);

    expect(Store::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Opening hours validation
|--------------------------------------------------------------------------
*/

test('an opening hours row cannot end on a day before it starts', function () {
    $this->actingAs(storeAdmin());

    Livewire::test('store.store-form-modal')
        ->call('openForm')
        ->set('name', 'Toko Hari')
        ->set('city', 'Bandung')
        ->call('addSchedule')
        ->set('schedules.0.day_from', '5')
        ->set('schedules.0.day_to', '2')
        ->set('schedules.0.opens_at', '10:00')
        ->set('schedules.0.closes_at', '21:00')
        ->call('saveStore')
        ->assertHasErrors(['schedules.0.day_to' => 'gte']);

    expect(Store::count())->toBe(0);
});

test('an opening hours row needs both times', function () {
    $this->actingAs(storeAdmin());

    Livewire::test('store.store-form-modal')
        ->call('openForm')
        ->set('name', 'Toko Jam')
        ->set('city', 'Bandung')
        ->call('addSchedule')
        ->call('saveStore')
        ->assertHasErrors([
            'schedules.0.opens_at' => 'required',
            'schedules.0.closes_at' => 'required',
        ]);

    expect(Store::count())->toBe(0);
});

test('opening hours reject invalid times and days', function () {
    $this->actingAs(storeAdmin());

    Livewire::test('store.store-form-modal')
        ->call('openForm')
        ->set('name', 'Toko Salah')
        ->set('city', 'Bandung')
        ->call('addSchedule')
        ->set('schedules.0.day_from', '9')
        ->set('schedules.0.opens_at', '25:99')
        ->set('schedules.0.closes_at', 'malam')
        ->call('saveStore')
        ->assertHasErrors([
            'schedules.0.day_from' => 'between',
            'schedules.0.opens_at' => 'date_format',
            'schedules.0.closes_at' => 'date_format',
        ]);

    expect(Store::count())->toBe(0);
});

test('opening hours are limited to seven rows', function () {
    $this->actingAs(storeAdmin());

    Livewire::test('store.store-form-modal')
        ->call('openForm')
        ->set('name', 'Toko Banyak')
        ->set('city', 'Bandung')
        ->set('schedules', array_fill(0, 8, [
            'day_from' => 1, 'day_to' => 7, 'opens_at' => '10:00', 'closes_at' => '22:00',
        ]))
        ->call('saveStore')
        ->assertHasErrors(['schedules' => 'max']);

    expect(Store::count())->toBe(0);
});

test('opening hours are formatted as readable day ranges', function () {
    $store = new Store(['opening_hours' => [
        ['day_from' => 1, 'day_to' => 5, 'opens_at' => '10:00', 'closes_at' => '21:00'],
        ['day_from' => 7, 'day_to' => 7, 'opens_at' => '09:00', 'closes_at' => '22:00'],
    ]]);

    expect($store->openingHoursLabels())->toBe([
        'Senin–Jumat 10.00–21.00',
        'Minggu 09.00–22.00',
    ])->and((new Store)->openingHoursLabels())->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Edit
|--------------------------------------------------------------------------
*/

test('admin can edit a store through the form modal', function () {
    $store = Store::factory()->create(['name' => 'Nama Lama', 'city' => 'Jakarta']);

    $this->actingAs(storeAdmin());

    Livewire::test('store.store-form-modal')
        ->call('openForm', $store->id)
        ->assertSet('storeId', $store->id)
        ->assertSet('name', 'Nama Lama')
        ->assertSet('schedules', $store->opening_hours)
        ->set('name', 'Nama Baru')
        ->set('city', 'Bandung')
        ->call('saveStore')
        ->assertHasNoErrors();

    expect($store->fresh())
        ->name->toBe('Nama Baru')
        ->city->toBe('Bandung');
    expect(Store::count())->toBe(1);
});

test('an opening hours row can be removed while editing', function () {
    $store = Store::factory()->create();

    $this->actingAs(storeAdmin());

    Livewire::test('store.store-form-modal')
        ->call('openForm', $store->id)
        ->call('removeSchedule', 0)
        ->assertSet('schedules', [])
        ->call('saveStore')
        ->assertHasNoErrors();

    expect($store->fresh()->opening_hours)->toBeNull();
});

test('admin can reactivate an inactive store through the edit form', function () {
    $store = Store::factory()->inactive()->create();

    $this->actingAs(storeAdmin());

    Livewire::test('store.store-form-modal')
        ->call('openForm', $store->id)
        ->assertSet('status', 'inactive')
        ->set('status', 'active')
        ->call('saveStore')
        ->assertHasNoErrors();

    expect($store->fresh()->status)->toBe('active');
});

/*
|--------------------------------------------------------------------------
| Deactivate (stores are never hard-deleted)
|--------------------------------------------------------------------------
*/

test('admin can deactivate a store through the deactivate modal', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));
    $store = Store::factory()->create();
    $other = Store::factory()->create();

    $this->actingAs($admin);

    Livewire::test('store.store-deactivate-modal')
        ->call('openDeactivate', $store->id, $store->name)
        ->call('deactivateStore')
        ->assertHasNoErrors();

    // Status flips to inactive...
    expect($store->fresh()->status)->toBe('inactive');
    $this->assertDatabaseHas('stores', ['id' => $store->id, 'status' => 'inactive']);
    // ...the row is still in the database (deactivated, not deleted)...
    expect(Store::find($store->id))->not->toBeNull();
    expect(Store::count())->toBe(2);
    // ...and nothing else was touched.
    expect($other->fresh()->status)->toBe('active');

    // A user without store:store-delete cannot deactivate.
    $plainUser = User::factory()->create();
    $plainUser->assignRole(config('alh.default_role'));

    $this->actingAs($plainUser);

    Livewire::test('store.store-deactivate-modal')
        ->call('openDeactivate', $other->id, $other->name)
        ->call('deactivateStore')
        ->assertForbidden();

    expect($other->fresh()->status)->toBe('active');
});

test('the store page still lists deactivated stores', function () {
    $store = Store::factory()->inactive()->create(['name' => 'Toko Tutup']);

    $this->actingAs(storeAdmin());

    Livewire::test('pages::store.index')
        ->assertSeeHtml(storeRow($store))
        ->assertSee('Toko Tutup');
});

/**
 * This is the fix for the known gap in the User Management module (see
 * SPEC §8.4 / EXTRACTION_LOG): a Livewire component's public methods are
 * reachable directly over the wire, so a role without store:store-create
 * must be rejected by saveStore() itself, not only by the page that
 * dispatches the "open-store-form" event.
 *
 * Note: Livewire::test()->call() does not let an aborted request bubble up
 * as a PHP exception — Livewire routes the component update through
 * Laravel's normal exception handling and hands back an HTTP-style
 * response, so the response itself is asserted (assertForbidden()) rather
 * than wrapping the call in expect(fn () => ...)->toThrow(...).
 */
test('a role without store:store-create cannot call saveStore directly', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    $this->actingAs($user);

    Livewire::test('store.store-form-modal')
        ->set('name', 'Toko Tanpa Izin')
        ->set('city', 'Bandung')
        ->call('saveStore')
        ->assertForbidden();

    expect(Store::where('name', 'Toko Tanpa Izin')->exists())->toBeFalse();
});

test('a role without store:store-delete cannot call deactivateStore directly', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));
    $store = Store::factory()->create();

    $this->actingAs($user);

    Livewire::test('store.store-deactivate-modal')
        ->call('openDeactivate', $store->id, $store->name)
        ->call('deactivateStore')
        ->assertForbidden();

    expect(Store::find($store->id))->not->toBeNull();
    expect($store->fresh()->status)->toBe('active');
});

/*
|--------------------------------------------------------------------------
| Access without permission (per action)
|--------------------------------------------------------------------------
*/

test('a role without create or edit cannot open the store form', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));
    $store = Store::factory()->create();

    $this->actingAs($user);

    Livewire::test('store.store-form-modal')
        ->call('openForm')
        ->assertForbidden();

    Livewire::test('store.store-form-modal')
        ->call('openForm', $store->id)
        ->assertForbidden();
});

test('a role with only store:store-create cannot edit an existing store', function () {
    $store = Store::factory()->create(['name' => 'Nama Asli']);

    $this->actingAs(storeUserWith(['store:store-view', 'store:store-create']));

    // The edit target is a public property, so a client can set it directly
    // instead of going through openForm().
    Livewire::test('store.store-form-modal')
        ->set('storeId', $store->id)
        ->set('name', 'Diubah Paksa')
        ->set('city', 'Bandung')
        ->call('saveStore')
        ->assertForbidden();

    expect($store->fresh()->name)->toBe('Nama Asli');
});

test('a role with only store:store-edit cannot create a store', function () {
    $this->actingAs(storeUserWith(['store:store-view', 'store:store-edit']));

    Livewire::test('store.store-form-modal')
        ->set('name', 'Toko Baru')
        ->set('city', 'Bandung')
        ->call('saveStore')
        ->assertForbidden();

    expect(Store::count())->toBe(0);
});

test('a role with edit but not delete cannot deactivate a store through the form', function () {
    $store = Store::factory()->create(['name' => 'Toko Aktif']);

    $this->actingAs(storeUserWith(['store:store-view', 'store:store-edit']));

    // Other edits are fine...
    Livewire::test('store.store-form-modal')
        ->call('openForm', $store->id)
        ->set('name', 'Toko Aktif Baru')
        ->call('saveStore')
        ->assertHasNoErrors();

    expect($store->fresh())->name->toBe('Toko Aktif Baru')->status->toBe('active');

    // ...but flipping the status to inactive is the same act as deactivating.
    Livewire::test('store.store-form-modal')
        ->call('openForm', $store->id)
        ->set('status', 'inactive')
        ->call('saveStore')
        ->assertForbidden();

    expect($store->fresh()->status)->toBe('active');

    // And so is the deactivate modal.
    Livewire::test('store.store-deactivate-modal')
        ->call('openDeactivate', $store->id, $store->name)
        ->call('deactivateStore')
        ->assertForbidden();

    expect($store->fresh()->status)->toBe('active');
});

test('a role with edit but not delete can still reactivate a store', function () {
    $store = Store::factory()->inactive()->create();

    $this->actingAs(storeUserWith(['store:store-view', 'store:store-edit']));

    Livewire::test('store.store-form-modal')
        ->call('openForm', $store->id)
        ->set('status', 'active')
        ->call('saveStore')
        ->assertHasNoErrors();

    expect($store->fresh()->status)->toBe('active');
});

test('a role with delete can deactivate a store', function () {
    $store = Store::factory()->create();

    $this->actingAs(storeUserWith(['store:store-view', 'store:store-delete']));

    Livewire::test('store.store-deactivate-modal')
        ->call('openDeactivate', $store->id, $store->name)
        ->call('deactivateStore')
        ->assertHasNoErrors();

    expect($store->fresh()->status)->toBe('inactive');
});

/*
|--------------------------------------------------------------------------
| List: search, filters, sorting
|--------------------------------------------------------------------------
*/

test('the store page lists stores with location, contact and opening hours', function () {
    // Values that appear nowhere else on the page (placeholders, dropdowns), so
    // that assertSeeInOrder() can only be satisfied by this store's own row.
    // The city is also a filter option, hence it is asserted in order, after
    // the area, which only the row renders.
    $store = Store::factory()->create([
        'name' => 'Bakery Sentosa',
        'city' => 'Semarang',
        'area' => 'Pasar Johar',
        'contact_name' => 'Rina Wijaya',
        'contact_phone' => '085599887766',
        'opening_hours' => [
            ['day_from' => 1, 'day_to' => 5, 'opens_at' => '10:00', 'closes_at' => '21:00'],
            ['day_from' => 6, 'day_to' => 7, 'opens_at' => '09:00', 'closes_at' => '22:00'],
        ],
    ]);

    $this->actingAs(storeAdmin());

    Livewire::test('pages::store.index')
        ->assertSeeHtml(storeRow($store))
        ->assertSeeInOrder([
            'Bakery Sentosa',
            'Pasar Johar',
            'Semarang',
            'Rina Wijaya',
            '085599887766',
            'Senin–Jumat 10.00–21.00',
            'Sabtu–Minggu 09.00–22.00',
        ]);
});

test('stores can be searched by name, city, area and contact', function () {
    $match = Store::factory()->create(['name' => 'Bakery Sentosa', 'city' => 'Semarang', 'area' => 'Pasar Johar', 'contact_name' => 'Rina Wijaya']);
    $other = Store::factory()->create(['name' => 'Toko Roti Makmur', 'city' => 'Medan', 'area' => 'Pasar Petisah', 'contact_name' => 'Sinta Dewi']);

    $this->actingAs(storeAdmin());

    foreach (['Sentosa', 'Semarang', 'Johar', 'Rina'] as $term) {
        Livewire::test('pages::store.index')
            ->set('search', $term)
            ->assertSeeHtml(storeRow($match))
            ->assertDontSeeHtml(storeRow($other));
    }
});

test('stores can be filtered by city and status', function () {
    $active = Store::factory()->create(['name' => 'Bakery Sentosa', 'city' => 'Semarang']);
    $inactive = Store::factory()->inactive()->create(['name' => 'Toko Tutup', 'city' => 'Semarang']);
    $elsewhere = Store::factory()->create(['name' => 'Toko Roti Makmur', 'city' => 'Medan']);

    $this->actingAs(storeAdmin());

    Livewire::test('pages::store.index')
        ->set('tempFilterCity', 'Semarang')
        ->call('applyFilters')
        ->assertSeeHtml(storeRow($active))
        ->assertSeeHtml(storeRow($inactive))
        ->assertDontSeeHtml(storeRow($elsewhere))
        ->set('tempFilterStatus', 'inactive')
        ->call('applyFilters')
        ->assertSeeHtml(storeRow($inactive))
        ->assertDontSeeHtml(storeRow($active))
        ->call('resetFilters')
        ->assertSeeHtml(storeRow($active))
        ->assertSeeHtml(storeRow($inactive))
        ->assertSeeHtml(storeRow($elsewhere));
});

test('a tampered sort column or direction falls back instead of erroring', function () {
    $store = Store::factory()->create(['name' => 'Bakery Sentosa']);

    $this->actingAs(storeAdmin());

    DB::enableQueryLog();

    Livewire::test('pages::store.index')
        ->set('sortColumn', 'nonexistent_column')
        ->set('sortDirection', 'sideways')
        ->assertOk()
        ->assertSeeHtml(storeRow($store));

    // SQLite quietly treats an unknown quoted column as a string, so "did not
    // error" proves nothing: check the SQL that actually ran (MySQL would
    // reject the unknown column with a 500).
    $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");

    expect($sql)->not->toContain('nonexistent_column')
        ->and($sql)->not->toContain('sideways')
        ->and($sql)->toContain('order by "id" asc');
});

/*
|--------------------------------------------------------------------------
| Seeded roles
|--------------------------------------------------------------------------
*/

test('the seeder gives admin every store permission and the default role none', function () {
    $storePermissions = [
        'store:store-view',
        'store:store-create',
        'store:store-edit',
        'store:store-delete',
    ];

    $admin = Role::findByName(config('alh.super_admin_role'));
    $default = Role::findByName(config('alh.default_role'));

    expect(config('alh.default_role'))->toBe('user')
        ->and(Role::pluck('name')->sort()->values()->all())->toBe(['admin', 'mitra', 'user'])
        ->and($admin->permissions->pluck('name')->all())->toContain(...$storePermissions)
        ->and($default->permissions->pluck('name')->all())->not->toContain(...$storePermissions);
});
