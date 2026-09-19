<?php

use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
});

test('guest is redirected to signin', function () {
    $this->get(route('vendor.index'))->assertRedirect(route('signin'));
});

test('user without vendor:vendor-view permission gets 403', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    $this->actingAs($user)
        ->get(route('vendor.index'))
        ->assertForbidden();
});

test('admin can view the vendor page', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    $this->actingAs($admin)
        ->get(route('vendor.index'))
        ->assertSuccessful()
        ->assertSeeLivewire('pages::vendor.index');
});

test('admin can create a vendor through the form modal', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    $this->actingAs($admin);

    Livewire::test('vendor.vendor-form-modal')
        ->call('openForm')
        ->set('name', 'PT Sumber Makmur')
        ->set('email', 'contact@sumbermakmur.test')
        ->set('status', 'active')
        ->call('saveVendor')
        ->assertHasNoErrors();

    expect(Vendor::where('name', 'PT Sumber Makmur')->exists())->toBeTrue();
});

test('admin can delete a vendor through the delete modal', function () {
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin);

    Livewire::test('vendor.vendor-delete-modal')
        ->call('openDelete', $vendor->id, $vendor->name)
        ->call('deleteVendor');

    expect(Vendor::find($vendor->id))->toBeNull();
});

/**
 * This is the fix for the known gap in the User Management module (see
 * SPEC §8.4 / EXTRACTION_LOG): a Livewire component's public methods are
 * reachable directly over the wire, so a role without vendor:vendor-create
 * must be rejected by saveVendor() itself, not only by the page that
 * dispatches the "open-vendor-form" event.
 *
 * Note: Livewire::test()->call() does not let an aborted request bubble up
 * as a PHP exception — Livewire routes the component update through
 * Laravel's normal exception handling and hands back an HTTP-style
 * response, so the response itself is asserted (assertForbidden()) rather
 * than wrapping the call in expect(fn () => ...)->toThrow(...).
 */
test('a role without vendor:vendor-create cannot call saveVendor directly', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    $this->actingAs($user);

    Livewire::test('vendor.vendor-form-modal')
        ->set('name', 'Vendor Tanpa Izin')
        ->call('saveVendor')
        ->assertForbidden();

    expect(Vendor::where('name', 'Vendor Tanpa Izin')->exists())->toBeFalse();
});

test('a role without vendor:vendor-delete cannot call deleteVendor directly', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));
    $vendor = Vendor::factory()->create();

    $this->actingAs($user);

    Livewire::test('vendor.vendor-delete-modal')
        ->call('openDelete', $vendor->id, $vendor->name)
        ->call('deleteVendor')
        ->assertForbidden();

    expect(Vendor::find($vendor->id))->not->toBeNull();
});
