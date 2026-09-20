<?php

use App\Helpers\Rupiah;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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
function productUserWith(array $permissions): User
{
    $role = Role::create(['name' => 'product-test-'.uniqid()]);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function productAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    return $admin;
}

/**
 * The attribute that marks a product's row in the list table. Rows are asserted
 * through it rather than through their text, because the page also renders the
 * form modal and the filter dropdowns, whose text can match a product's.
 */
function productRow(Product $product): string
{
    return 'wire:key="product-'.$product->id.'"';
}

/*
|--------------------------------------------------------------------------
| Page access
|--------------------------------------------------------------------------
*/

test('guest is redirected to signin', function () {
    $this->get(route('product.index'))->assertRedirect(route('signin'));
});

test('user without product:product-view permission gets 403', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    $this->actingAs($user)
        ->get(route('product.index'))
        ->assertForbidden();
});

test('admin can view the product page', function () {
    $this->actingAs(productAdmin())
        ->get(route('product.index'))
        ->assertSuccessful()
        ->assertSeeLivewire('pages::product.index');
});

test('a role with only product:product-view can open the page', function () {
    $this->actingAs(productUserWith(['product:product-view']))
        ->get(route('product.index'))
        ->assertSuccessful();
});

test('the product route is protected by permission middleware, not only by the component', function () {
    $middleware = Route::getRoutes()->getByName('product.index')->gatherMiddleware();

    expect($middleware)->toContain('permission:product:product-view');
});

test('a role with only store permissions cannot see products', function () {
    $this->actingAs(productUserWith(['store:store-view', 'store:store-create', 'store:store-edit', 'store:store-delete']))
        ->get(route('product.index'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/

test('admin can create a product through the form modal', function () {
    $store = Store::factory()->create();

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Croissant Almond')
        ->set('buy_price', '48000')
        ->set('sell_price', '60000')
        ->call('saveProduct')
        ->assertHasNoErrors()
        ->assertSet('isAddEditOpen', false);

    expect(Product::where('name', 'Croissant Almond')->exists())->toBeTrue();
});

test('a product is saved with every field and belongs to the chosen store', function () {
    $store = Store::factory()->create();
    Store::factory()->create(); // another store the product must not end up in

    $this->actingAs(productAdmin());

    $this->freezeSecond();

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Croissant Almond')
        ->set('variant', 'Isi 6')
        ->set('sku', 'CRS-006')
        ->set('unit', 'box')
        ->set('buy_price', '48000')
        ->set('sell_price', '60000')
        ->set('notes', 'Stok terbatas')
        ->set('status', 'active')
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Croissant Almond')->firstOrFail();

    expect($product->store_id)->toBe($store->id)
        ->and($product->store->is($store))->toBeTrue()
        ->and($product->variant)->toBe('Isi 6')
        ->and($product->sku)->toBe('CRS-006')
        ->and($product->unit)->toBe('box')
        ->and($product->buy_price)->toBe(48000)
        ->and($product->sell_price)->toBe(60000)
        ->and($product->notes)->toBe('Stok terbatas')
        ->and($product->status)->toBe('active')
        ->and($product->buy_price_checked_at->equalTo(now()))->toBeTrue();
});

test('optional fields left blank are stored as null, and a blank variant as an empty string', function () {
    $store = Store::factory()->create();

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Roti Tawar')
        ->set('buy_price', '15000')
        ->set('sell_price', '18000')
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Roti Tawar')->firstOrFail();

    expect($product->sku)->toBeNull()
        ->and($product->notes)->toBeNull()
        ->and($product->variant)->toBe('')
        ->and($product->unit)->toBe('pcs')
        ->and($product->status)->toBe('active');
});

test('store, name, unit and both prices are required', function () {
    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('unit', '')
        ->call('saveProduct')
        ->assertHasErrors([
            'store_id' => 'required',
            'name' => 'required',
            'unit' => 'required',
            'buy_price' => 'required',
            'sell_price' => 'required',
        ]);

    expect(Product::count())->toBe(0);
});

test('prices must be whole, non-negative rupiah within range', function (string $field, string $value, string $rule) {
    $store = Store::factory()->create();

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Produk Harga')
        ->set('buy_price', '10000')
        ->set('sell_price', '12000')
        ->set($field, $value)
        ->call('saveProduct')
        ->assertHasErrors([$field => $rule]);

    expect(Product::count())->toBe(0);
})->with([
    'negative buy price' => ['buy_price', '-1', 'min'],
    'decimal buy price' => ['buy_price', '10.5', 'integer'],
    'text buy price' => ['buy_price', 'murah', 'integer'],
    'too large buy price' => ['buy_price', '1000000000', 'max'],
    'negative sell price' => ['sell_price', '-1', 'min'],
    'decimal sell price' => ['sell_price', '12.5', 'integer'],
    'too large sell price' => ['sell_price', '1000000000', 'max'],
]);

test('a price of zero is allowed', function () {
    $store = Store::factory()->create();

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Sampel Gratis')
        ->set('buy_price', '0')
        ->set('sell_price', '0')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect(Product::where('name', 'Sampel Gratis')->exists())->toBeTrue();
});

test('status must be active or inactive', function () {
    $store = Store::factory()->create();

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Produk Status')
        ->set('buy_price', '1000')
        ->set('sell_price', '2000')
        ->set('status', 'banned')
        ->call('saveProduct')
        ->assertHasErrors(['status' => 'in']);

    expect(Product::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| A product belongs to a store
|--------------------------------------------------------------------------
*/

test('a product cannot be created for a store that does not exist', function () {
    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('store_id', '99999')
        ->set('name', 'Produk Yatim')
        ->set('buy_price', '1000')
        ->set('sell_price', '2000')
        ->call('saveProduct')
        ->assertHasErrors(['store_id' => 'exists']);

    expect(Product::count())->toBe(0);
});

test('a product cannot be created for an inactive store', function () {
    $store = Store::factory()->inactive()->create();

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Produk Toko Tutup')
        ->set('buy_price', '1000')
        ->set('sell_price', '2000')
        ->call('saveProduct')
        ->assertHasErrors(['store_id' => 'exists']);

    expect(Product::count())->toBe(0);
});

test('the create form only offers active stores', function () {
    $active = Store::factory()->create(['name' => 'Toko Aktif Sentosa']);
    $inactive = Store::factory()->inactive()->create(['name' => 'Toko Tutup Sentosa']);

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->assertSeeHtml('<option value="'.$active->id.'">')
        ->assertDontSeeHtml('<option value="'.$inactive->id.'">');
});

test('the store of a product cannot be changed while editing', function () {
    $original = Store::factory()->create();
    $other = Store::factory()->create();
    $product = Product::factory()->for($original)->create(['name' => 'Roti Pindah']);

    $this->actingAs(productAdmin());

    // store_id is a public property, so a client can set it to anything.
    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->assertSet('store_id', (string) $original->id)
        ->set('store_id', (string) $other->id)
        ->set('name', 'Roti Pindah Baru')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->fresh())
        ->name->toBe('Roti Pindah Baru')
        ->store_id->toBe($original->id);
});

test('a product still belongs to its store after the store is deactivated', function () {
    $store = Store::factory()->create();
    $product = Product::factory()->for($store)->create();

    $this->actingAs(productAdmin());

    Livewire::test('store.store-deactivate-modal')
        ->call('openDeactivate', $store->id, $store->name)
        ->call('deactivateStore')
        ->assertHasNoErrors();

    expect($store->fresh()->status)->toBe('inactive')
        ->and($product->fresh()->status)->toBe('active')
        ->and($product->fresh()->store_id)->toBe($store->id)
        ->and($store->products()->count())->toBe(1);
});

test('a store lists only its own products', function () {
    $store = Store::factory()->create();
    $mine = Product::factory()->for($store)->create();
    $theirs = Product::factory()->create();

    expect($store->products->pluck('id')->all())->toBe([$mine->id])
        ->and($theirs->store_id)->not->toBe($store->id);
});

test('the same product name and variant is rejected within a store', function () {
    $store = Store::factory()->create();
    Product::factory()->for($store)->create(['name' => 'Croissant', 'variant' => 'Almond']);

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Croissant')
        ->set('variant', 'Almond')
        ->set('buy_price', '1000')
        ->set('sell_price', '2000')
        ->call('saveProduct')
        ->assertHasErrors(['name' => 'unique']);

    expect(Product::count())->toBe(1);
});

test('the same name without a variant is rejected twice within a store', function () {
    $store = Store::factory()->create();
    Product::factory()->for($store)->create(['name' => 'Roti Tawar', 'variant' => '']);

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Roti Tawar')
        ->set('buy_price', '1000')
        ->set('sell_price', '2000')
        ->call('saveProduct')
        ->assertHasErrors(['name' => 'unique']);

    expect(Product::count())->toBe(1);
});

test('the same name with a different variant is a separate product', function () {
    $store = Store::factory()->create();
    Product::factory()->for($store)->create(['name' => 'Croissant', 'variant' => 'Almond']);

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Croissant')
        ->set('variant', 'Cokelat')
        ->set('buy_price', '1000')
        ->set('sell_price', '2000')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect(Product::where('name', 'Croissant')->count())->toBe(2);
});

test('the same name and variant is fine in a different store', function () {
    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    Product::factory()->for($otherStore)->create(['name' => 'Croissant', 'variant' => 'Almond']);

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Croissant')
        ->set('variant', 'Almond')
        ->set('buy_price', '1000')
        ->set('sell_price', '2000')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect(Product::where('name', 'Croissant')->count())->toBe(2);
});

test('surrounding spaces do not make a duplicate look different', function () {
    $store = Store::factory()->create();
    Product::factory()->for($store)->create(['name' => 'Roti Tawar', 'variant' => '']);

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('store_id', (string) $store->id)
        ->set('name', '  Roti Tawar  ')
        ->set('buy_price', '1000')
        ->set('sell_price', '2000')
        ->call('saveProduct')
        ->assertHasErrors(['name' => 'unique']);
});

test('the database itself rejects a duplicate store, name and variant', function () {
    $store = Store::factory()->create();
    Product::factory()->for($store)->create(['name' => 'Croissant', 'variant' => 'Almond']);

    expect(fn () => Product::factory()->for($store)->create(['name' => 'Croissant', 'variant' => 'Almond']))
        ->toThrow(QueryException::class);
});

/*
|--------------------------------------------------------------------------
| Estimated margin (derived, never stored)
|--------------------------------------------------------------------------
*/

test('estimated margin is the sell price minus the buy price', function () {
    $product = new Product(['buy_price' => 48000, 'sell_price' => 60000]);

    expect($product->estimatedMargin())->toBe(12000)
        ->and($product->estimatedMarginPercent())->toBe(20.0);
});

test('estimated margin can be negative when selling below cost', function () {
    $product = new Product(['buy_price' => 60000, 'sell_price' => 48000]);

    expect($product->estimatedMargin())->toBe(-12000)
        ->and($product->estimatedMarginPercent())->toBe(-25.0);
});

test('estimated margin percent is null when the sell price is zero', function () {
    $product = new Product(['buy_price' => 1000, 'sell_price' => 0]);

    expect($product->estimatedMargin())->toBe(-1000)
        ->and($product->estimatedMarginPercent())->toBeNull();
});

test('the margin is not a database column', function () {
    expect(Schema::hasColumn('products', 'margin'))->toBeFalse()
        ->and(Schema::hasColumn('products', 'estimated_margin'))->toBeFalse();
});

test('rupiah are formatted with dot separators and a sign for negatives', function () {
    expect(Rupiah::format(25000))->toBe('Rp 25.000')
        ->and(Rupiah::format(0))->toBe('Rp 0')
        ->and(Rupiah::format(1234567))->toBe('Rp 1.234.567')
        ->and(Rupiah::format(-5000))->toBe('-Rp 5.000');
});

test('the form previews the estimated margin as prices are typed', function () {
    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->assertSee('Estimasi margin')
        ->set('buy_price', '48000')
        ->set('sell_price', '60000')
        ->assertSee('Rp 12.000')
        ->assertSee('20,0%')
        ->set('sell_price', '40000')
        ->assertSee('-Rp 8.000');
});

test('the product list shows the buy price, sell price and estimated margin', function () {
    $product = Product::factory()->create([
        'name' => 'Roti Unik Sentosa',
        'buy_price' => 48000,
        'sell_price' => 60000,
    ]);

    $this->actingAs(productAdmin());

    Livewire::test('pages::product.index')
        ->assertSeeHtml(productRow($product))
        ->assertSeeInOrder([
            'Roti Unik Sentosa',
            'Rp 48.000',
            'Rp 60.000',
            'Rp 12.000',
            '20,0%',
        ]);
});

/*
|--------------------------------------------------------------------------
| Buy price check time
|--------------------------------------------------------------------------
*/

test('changing the buy price updates when it was last checked', function () {
    $product = Product::factory()->create(['buy_price' => 48000, 'buy_price_checked_at' => now()->subDays(10)]);

    $this->actingAs(productAdmin());

    $this->freezeSecond();

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->set('buy_price', '50000')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->fresh())
        ->buy_price->toBe(50000)
        ->and($product->fresh()->buy_price_checked_at->equalTo(now()))->toBeTrue();
});

test('editing something else leaves the price check time alone', function () {
    $checked = now()->subDays(10)->startOfSecond();
    $product = Product::factory()->create(['buy_price' => 48000, 'buy_price_checked_at' => $checked]);

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->set('notes', 'Catatan baru')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->fresh()->buy_price_checked_at->equalTo($checked))->toBeTrue();
});

test('confirming the price refreshes the check time without changing the price', function () {
    $product = Product::factory()->create(['buy_price' => 48000, 'buy_price_checked_at' => now()->subDays(10)]);

    $this->actingAs(productAdmin());

    $this->freezeSecond();

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->set('buy_price_confirmed', true)
        ->call('saveProduct')
        ->assertHasNoErrors()
        ->assertSet('buy_price_confirmed', false);

    expect($product->fresh())->buy_price->toBe(48000)
        ->and($product->fresh()->buy_price_checked_at->equalTo(now()))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Edit
|--------------------------------------------------------------------------
*/

test('admin can edit a product through the form modal', function () {
    $product = Product::factory()->create(['name' => 'Nama Lama', 'variant' => 'Kecil', 'unit' => 'pcs']);

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->assertSet('productId', $product->id)
        ->assertSet('name', 'Nama Lama')
        ->assertSet('variant', 'Kecil')
        ->assertSet('buy_price', (string) $product->buy_price)
        ->set('name', 'Nama Baru')
        ->set('variant', 'Besar')
        ->set('unit', 'box')
        ->set('sell_price', '99000')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->fresh())
        ->name->toBe('Nama Baru')
        ->variant->toBe('Besar')
        ->unit->toBe('box')
        ->sell_price->toBe(99000);
    expect(Product::count())->toBe(1);
});

test('a product can be saved again without tripping its own unique key', function () {
    $product = Product::factory()->create(['name' => 'Croissant', 'variant' => 'Almond']);

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->call('saveProduct')
        ->assertHasNoErrors();
});

test('editing cannot collide with another product of the same store', function () {
    $store = Store::factory()->create();
    Product::factory()->for($store)->create(['name' => 'Croissant', 'variant' => 'Almond']);
    $other = Product::factory()->for($store)->create(['name' => 'Croissant', 'variant' => 'Cokelat']);

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm', $other->id)
        ->set('variant', 'Almond')
        ->call('saveProduct')
        ->assertHasErrors(['name' => 'unique']);

    expect($other->fresh()->variant)->toBe('Cokelat');
});

test('admin can reactivate an inactive product through the edit form', function () {
    $product = Product::factory()->inactive()->create();

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->assertSet('status', 'inactive')
        ->set('status', 'active')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->fresh()->status)->toBe('active');
});

test('a product of an inactive store can still be edited', function () {
    $store = Store::factory()->inactive()->create();
    $product = Product::factory()->for($store)->create(['name' => 'Produk Lama']);

    $this->actingAs(productAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->set('name', 'Produk Diperbarui')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->fresh()->name)->toBe('Produk Diperbarui');
});

/*
|--------------------------------------------------------------------------
| Deactivate (products are never hard-deleted)
|--------------------------------------------------------------------------
*/

test('admin can deactivate a product through the deactivate modal', function () {
    $admin = productAdmin();
    $product = Product::factory()->create();
    $other = Product::factory()->create();

    $this->actingAs($admin);

    Livewire::test('product.product-deactivate-modal')
        ->call('openDeactivate', $product->id, $product->name)
        ->call('deactivateProduct')
        ->assertHasNoErrors();

    // Status flips to inactive...
    expect($product->fresh()->status)->toBe('inactive');
    $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'inactive']);
    // ...the row is still in the database (deactivated, not deleted)...
    expect(Product::find($product->id))->not->toBeNull();
    expect(Product::count())->toBe(2);
    // ...and nothing else was touched.
    expect($other->fresh()->status)->toBe('active');

    // A user without product:product-delete cannot deactivate.
    $plainUser = User::factory()->create();
    $plainUser->assignRole(config('alh.default_role'));

    $this->actingAs($plainUser);

    Livewire::test('product.product-deactivate-modal')
        ->call('openDeactivate', $other->id, $other->name)
        ->call('deactivateProduct')
        ->assertForbidden();

    expect($other->fresh()->status)->toBe('active');
});

test('the product page still lists deactivated products', function () {
    $product = Product::factory()->inactive()->create();

    $this->actingAs(productAdmin());

    Livewire::test('pages::product.index')
        ->assertSeeHtml(productRow($product));
});

test('the products table has no soft delete column', function () {
    expect(Schema::hasColumn('products', 'deleted_at'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Access without permission (per action)
|--------------------------------------------------------------------------
*/

test('a role without product:product-create cannot call saveProduct directly', function () {
    $store = Store::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    $this->actingAs($user);

    Livewire::test('product.product-form-modal')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Produk Tanpa Izin')
        ->set('buy_price', '1000')
        ->set('sell_price', '2000')
        ->call('saveProduct')
        ->assertForbidden();

    expect(Product::where('name', 'Produk Tanpa Izin')->exists())->toBeFalse();
});

test('a role without product:product-delete cannot call deactivateProduct directly', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));
    $product = Product::factory()->create();

    $this->actingAs($user);

    Livewire::test('product.product-deactivate-modal')
        ->call('openDeactivate', $product->id, $product->name)
        ->call('deactivateProduct')
        ->assertForbidden();

    expect(Product::find($product->id))->not->toBeNull();
    expect($product->fresh()->status)->toBe('active');
});

test('a role without create or edit cannot open the product form', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));
    $product = Product::factory()->create();

    $this->actingAs($user);

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->assertForbidden();

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->assertForbidden();
});

test('a role with only product:product-create cannot edit an existing product', function () {
    $product = Product::factory()->create(['name' => 'Nama Asli']);

    $this->actingAs(productUserWith(['product:product-view', 'product:product-create']));

    // The edit target is a public property, so a client can set it directly
    // instead of going through openForm().
    Livewire::test('product.product-form-modal')
        ->set('productId', $product->id)
        ->set('name', 'Diubah Paksa')
        ->set('buy_price', '1')
        ->set('sell_price', '2')
        ->call('saveProduct')
        ->assertForbidden();

    expect($product->fresh()->name)->toBe('Nama Asli');
});

test('a role with only product:product-edit cannot create a product', function () {
    $store = Store::factory()->create();

    $this->actingAs(productUserWith(['product:product-view', 'product:product-edit']));

    Livewire::test('product.product-form-modal')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Produk Baru')
        ->set('buy_price', '1000')
        ->set('sell_price', '2000')
        ->call('saveProduct')
        ->assertForbidden();

    expect(Product::count())->toBe(0);
});

test('a role with edit but not delete cannot deactivate a product through the form', function () {
    $product = Product::factory()->create(['name' => 'Produk Aktif']);

    $this->actingAs(productUserWith(['product:product-view', 'product:product-edit']));

    // Other edits are fine...
    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->set('name', 'Produk Aktif Baru')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->fresh())->name->toBe('Produk Aktif Baru')->status->toBe('active');

    // ...but flipping the status to inactive is the same act as deactivating.
    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->set('status', 'inactive')
        ->call('saveProduct')
        ->assertForbidden();

    expect($product->fresh()->status)->toBe('active');

    // And so is the deactivate modal.
    Livewire::test('product.product-deactivate-modal')
        ->call('openDeactivate', $product->id, $product->name)
        ->call('deactivateProduct')
        ->assertForbidden();

    expect($product->fresh()->status)->toBe('active');
});

test('a role with edit but not delete can still reactivate a product', function () {
    $product = Product::factory()->inactive()->create();

    $this->actingAs(productUserWith(['product:product-view', 'product:product-edit']));

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->set('status', 'active')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->fresh()->status)->toBe('active');
});

test('a role with delete can deactivate a product', function () {
    $product = Product::factory()->create();

    $this->actingAs(productUserWith(['product:product-view', 'product:product-delete']));

    Livewire::test('product.product-deactivate-modal')
        ->call('openDeactivate', $product->id, $product->name)
        ->call('deactivateProduct')
        ->assertHasNoErrors();

    expect($product->fresh()->status)->toBe('inactive');
});

test('store permissions do not grant anything on products', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();

    $this->actingAs(productUserWith(['store:store-view', 'store:store-create', 'store:store-edit', 'store:store-delete']));

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->assertForbidden();

    Livewire::test('product.product-form-modal')
        ->set('store_id', (string) $store->id)
        ->set('name', 'Lewat Izin Toko')
        ->set('buy_price', '1000')
        ->set('sell_price', '2000')
        ->call('saveProduct')
        ->assertForbidden();

    Livewire::test('product.product-deactivate-modal')
        ->call('openDeactivate', $product->id, $product->name)
        ->call('deactivateProduct')
        ->assertForbidden();

    expect(Product::count())->toBe(1)
        ->and($product->fresh()->status)->toBe('active');
});

/*
|--------------------------------------------------------------------------
| List: search, filters, sorting
|--------------------------------------------------------------------------
*/

test('the product list shows the store, variant, unit and code of each product', function () {
    $store = Store::factory()->create(['name' => 'Bakery Sentosa', 'city' => 'Semarang']);
    $product = Product::factory()->for($store)->create([
        'name' => 'Croissant Unik',
        'variant' => 'Isi Enam',
        'unit' => 'box',
        'sku' => 'CRS-006-X',
    ]);

    $this->actingAs(productAdmin());

    Livewire::test('pages::product.index')
        ->assertSeeHtml(productRow($product))
        ->assertSeeInOrder([
            'Croissant Unik',
            'Isi Enam',
            'Satuan: box',
            'Kode: CRS-006-X',
            'Bakery Sentosa',
        ]);
});

test('a product of an inactive store is marked as such in the list', function () {
    $store = Store::factory()->inactive()->create(['name' => 'Toko Tutup Sentosa']);
    Product::factory()->for($store)->create(['name' => 'Roti Toko Tutup']);

    $this->actingAs(productAdmin());

    Livewire::test('pages::product.index')
        ->assertSeeInOrder(['Roti Toko Tutup', 'Toko Tutup Sentosa', 'Toko nonaktif']);
});

test('products can be searched by name, variant, code and store name', function () {
    $store = Store::factory()->create(['name' => 'Bakery Sentosa']);
    $otherStore = Store::factory()->create(['name' => 'Toko Roti Makmur']);
    $match = Product::factory()->for($store)->create(['name' => 'Croissant Unik', 'variant' => 'Isi Enam', 'sku' => 'CRS-006-X']);
    $other = Product::factory()->for($otherStore)->create(['name' => 'Donat Gula', 'variant' => 'Besar', 'sku' => 'DNT-001']);

    $this->actingAs(productAdmin());

    foreach (['Croissant', 'Isi Enam', 'CRS-006', 'Bakery Sentosa'] as $term) {
        Livewire::test('pages::product.index')
            ->set('search', $term)
            ->assertSeeHtml(productRow($match))
            ->assertDontSeeHtml(productRow($other));
    }
});

test('products can be filtered by store and status', function () {
    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $active = Product::factory()->for($store)->create();
    $inactive = Product::factory()->for($store)->inactive()->create();
    $elsewhere = Product::factory()->for($otherStore)->create();

    $this->actingAs(productAdmin());

    Livewire::test('pages::product.index')
        ->set('tempFilterStore', (string) $store->id)
        ->call('applyFilters')
        ->assertSeeHtml(productRow($active))
        ->assertSeeHtml(productRow($inactive))
        ->assertDontSeeHtml(productRow($elsewhere))
        ->set('tempFilterStatus', 'inactive')
        ->call('applyFilters')
        ->assertSeeHtml(productRow($inactive))
        ->assertDontSeeHtml(productRow($active))
        ->call('resetFilters')
        ->assertSeeHtml(productRow($active))
        ->assertSeeHtml(productRow($inactive))
        ->assertSeeHtml(productRow($elsewhere));
});

test('the store filter can be applied to a store that has been deactivated', function () {
    $store = Store::factory()->inactive()->create();
    $product = Product::factory()->for($store)->create();
    $elsewhere = Product::factory()->create();

    $this->actingAs(productAdmin());

    Livewire::test('pages::product.index')
        ->set('tempFilterStore', (string) $store->id)
        ->call('applyFilters')
        ->assertSeeHtml(productRow($product))
        ->assertDontSeeHtml(productRow($elsewhere));
});

test('a tampered store filter is ignored instead of erroring', function () {
    // Takes store id 1, so the product below is not in it: a filter that read
    // "1' OR '1'='1" as store 1 would wrongly hide the product.
    Store::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs(productAdmin());

    Livewire::test('pages::product.index')
        ->set('filterStore', "1' OR '1'='1")
        ->assertOk()
        ->assertSeeHtml(productRow($product));
});

test('products can be sorted by price', function () {
    $cheap = Product::factory()->create(['buy_price' => 1000, 'sell_price' => 2000]);
    $dear = Product::factory()->create(['buy_price' => 9000, 'sell_price' => 90000]);

    $this->actingAs(productAdmin());

    Livewire::test('pages::product.index')
        ->call('sortBy', 'sell_price')
        ->assertSeeHtmlInOrder([productRow($cheap), productRow($dear)])
        ->call('sortBy', 'sell_price')
        ->assertSeeHtmlInOrder([productRow($dear), productRow($cheap)]);
});

test('a tampered sort column or direction falls back instead of erroring', function () {
    $product = Product::factory()->create();

    $this->actingAs(productAdmin());

    DB::enableQueryLog();

    Livewire::test('pages::product.index')
        ->set('sortColumn', 'nonexistent_column')
        ->set('sortDirection', 'sideways')
        ->assertOk()
        ->assertSeeHtml(productRow($product));

    // SQLite quietly treats an unknown quoted column as a string, so "did not
    // error" proves nothing: check the SQL that actually ran (MySQL would
    // reject the unknown column with a 500).
    $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");

    expect($sql)->not->toContain('nonexistent_column')
        ->and($sql)->not->toContain('sideways')
        ->and($sql)->toContain('order by "id" asc');
});

test('the product list loads stores in one query, not one per row', function () {
    $this->actingAs(productAdmin());

    $storeQueryCount = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test('pages::product.index')->assertOk();

        return collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $sql) => str_contains($sql, 'from "stores"'))
            ->count();
    };

    Product::factory()->create();
    $withOneProduct = $storeQueryCount();

    Product::factory()->count(9)->create();
    $withTenProducts = $storeQueryCount();

    expect(Product::count())->toBe(10)
        ->and($withTenProducts)->toBe($withOneProduct);
});

/*
|--------------------------------------------------------------------------
| Seeded roles
|--------------------------------------------------------------------------
*/

test('the seeder gives admin every product permission and the default role none', function () {
    $productPermissions = [
        'product:product-view',
        'product:product-create',
        'product:product-edit',
        'product:product-delete',
    ];

    $admin = Role::findByName(config('alh.super_admin_role'));
    $default = Role::findByName(config('alh.default_role'));

    expect($admin->permissions->pluck('name')->all())->toContain(...$productPermissions)
        ->and($default->permissions->pluck('name')->all())->not->toContain(...$productPermissions);
});
