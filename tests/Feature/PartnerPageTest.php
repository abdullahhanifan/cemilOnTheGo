<?php

use App\Models\Partner;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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
function partnerUserWith(array $permissions): User
{
    $role = Role::create(['name' => 'partner-test-'.uniqid()]);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function partnerAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    return $admin;
}

/**
 * The attribute that marks a partner's row in the list table. Rows are asserted through it
 * rather than through their text, because the page also renders the form modal and the
 * filter dropdowns, whose text can match a partner's.
 */
function partnerRow(Partner $partner): string
{
    return 'wire:key="partner-'.$partner->id.'"';
}

/**
 * Fill the required fields of a form that is open for a new partner, then apply overrides.
 */
function fillPartnerForm($form, array $overrides = [])
{
    $values = array_merge([
        'name' => 'Siti Rahmawati',
        'phone' => '081234567890',
        'city' => 'Bandung',
    ], $overrides);

    foreach ($values as $field => $value) {
        $form = $form->set($field, $value);
    }

    return $form;
}

/*
|--------------------------------------------------------------------------
| Page access
|--------------------------------------------------------------------------
*/

test('guest is redirected to signin', function () {
    $this->get(route('partner.index'))->assertRedirect(route('signin'));
});

test('user without partner:partner-view permission gets 403', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    $this->actingAs($user)
        ->get(route('partner.index'))
        ->assertForbidden();
});

test('admin can view the partner page', function () {
    $this->actingAs(partnerAdmin())
        ->get(route('partner.index'))
        ->assertSuccessful()
        ->assertSeeLivewire('pages::partner.index');
});

test('a role with only partner:partner-view can open the page', function () {
    $this->actingAs(partnerUserWith(['partner:partner-view']))
        ->get(route('partner.index'))
        ->assertSuccessful();
});

test('the partner route is protected by permission middleware, not only by the component', function () {
    $middleware = Route::getRoutes()->getByName('partner.index')->gatherMiddleware();

    expect($middleware)->toContain('permission:partner:partner-view');
});

test('store and product permissions grant nothing on partners', function () {
    $permissions = [
        'store:store-view', 'store:store-create', 'store:store-edit', 'store:store-delete',
        'product:product-view', 'product:product-create', 'product:product-edit', 'product:product-delete',
    ];
    $partner = Partner::factory()->create();

    $this->actingAs(partnerUserWith($permissions))
        ->get(route('partner.index'))
        ->assertForbidden();

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->assertForbidden();

    Livewire::test('partner.partner-form-modal')
        ->tap(fn ($form) => fillPartnerForm($form))
        ->call('savePartner')
        ->assertForbidden();

    Livewire::test('partner.partner-deactivate-modal')
        ->call('openDeactivate', $partner->id, $partner->name)
        ->call('deactivatePartner')
        ->assertForbidden();

    expect(Partner::count())->toBe(1)->and($partner->fresh()->status)->toBe('active');
});

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/

test('admin can create a partner through the form modal', function () {
    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillPartnerForm($form))
        ->call('savePartner')
        ->assertHasNoErrors()
        ->assertSet('isAddEditOpen', false);

    expect(Partner::where('name', 'Siti Rahmawati')->exists())->toBeTrue();
});

test('a partner is saved with every field', function () {
    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillPartnerForm($form, [
            'area' => 'Dago',
            'payment_method' => 'bank',
            'payment_provider' => 'BCA',
            'account_name' => 'Siti Rahmawati',
            'account_number' => '1234567890',
            'notes' => 'Hanya bisa membeli di akhir pekan',
            'status' => 'active',
        ]))
        ->call('savePartner')
        ->assertHasNoErrors();

    $partner = Partner::where('name', 'Siti Rahmawati')->firstOrFail();

    expect($partner->phone)->toBe('081234567890')
        ->and($partner->city)->toBe('Bandung')
        ->and($partner->area)->toBe('Dago')
        ->and($partner->payment_method)->toBe('bank')
        ->and($partner->paymentMethodLabel())->toBe('Transfer bank')
        ->and($partner->payment_provider)->toBe('BCA')
        ->and($partner->account_name)->toBe('Siti Rahmawati')
        ->and($partner->account_number)->toBe('1234567890')
        ->and($partner->notes)->toBe('Hanya bisa membeli di akhir pekan')
        ->and($partner->status)->toBe('active');
});

test('optional fields left blank are stored as null', function () {
    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillPartnerForm($form, ['name' => 'Tanpa Rekening']))
        ->call('savePartner')
        ->assertHasNoErrors();

    $partner = Partner::where('name', 'Tanpa Rekening')->firstOrFail();

    expect($partner->area)->toBeNull()
        ->and($partner->payment_method)->toBeNull()
        ->and($partner->payment_provider)->toBeNull()
        ->and($partner->account_name)->toBeNull()
        ->and($partner->account_number)->toBeNull()
        ->and($partner->notes)->toBeNull()
        ->and($partner->status)->toBe('active')
        ->and($partner->user_id)->toBeNull();
});

test('name, phone and city are required', function () {
    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->call('savePartner')
        ->assertHasErrors(['name' => 'required', 'phone' => 'required', 'city' => 'required']);

    expect(Partner::count())->toBe(0);
});

test('status must be active or inactive', function () {
    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillPartnerForm($form, ['status' => 'banned']))
        ->call('savePartner')
        ->assertHasErrors(['status' => 'in']);

    expect(Partner::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Payment details: all filled or all empty
|--------------------------------------------------------------------------
*/

test('the payment fields go together', function (array $input, array $expectedErrors) {
    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillPartnerForm($form, $input))
        ->call('savePartner')
        ->assertHasErrors($expectedErrors);

    expect(Partner::count())->toBe(0);
})->with([
    'method only' => [
        ['payment_method' => 'bank'],
        ['account_name' => 'required_with', 'account_number' => 'required_with'],
    ],
    'account name only' => [
        ['account_name' => 'Siti'],
        ['payment_method' => 'required_with', 'account_number' => 'required_with'],
    ],
    'account number only' => [
        ['account_number' => '1234567890'],
        ['payment_method' => 'required_with', 'account_name' => 'required_with'],
    ],
    'method and name, no number' => [
        ['payment_method' => 'bank', 'account_name' => 'Siti'],
        ['account_number' => 'required_with'],
    ],
    'provider only' => [
        ['payment_provider' => 'BCA'],
        ['payment_method' => 'required_with', 'account_name' => 'required_with', 'account_number' => 'required_with'],
    ],
]);

test('payment values are validated', function (array $input, array $expectedErrors) {
    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillPartnerForm($form, $input))
        ->call('savePartner')
        ->assertHasErrors($expectedErrors);

    expect(Partner::count())->toBe(0);
})->with([
    'unknown method' => [
        ['payment_method' => 'cash', 'account_name' => 'Siti', 'account_number' => '1234567890'],
        ['payment_method' => 'in'],
    ],
    'letters in the number' => [
        ['payment_method' => 'bank', 'account_name' => 'Siti', 'account_number' => 'abc12345'],
        ['account_number' => 'regex'],
    ],
    'number too short' => [
        ['payment_method' => 'bank', 'account_name' => 'Siti', 'account_number' => '1234'],
        ['account_number' => 'regex'],
    ],
    'number too long' => [
        ['payment_method' => 'bank', 'account_name' => 'Siti', 'account_number' => '1234567890123456789012345678901'],
        ['account_number' => 'regex'],
    ],
]);

test('spaces and dashes in the account number are removed before saving', function () {
    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillPartnerForm($form, [
            'payment_method' => 'bank',
            'account_name' => 'Siti Rahmawati',
            'account_number' => ' 1234-5678 90 ',
        ]))
        ->call('savePartner')
        ->assertHasNoErrors();

    expect(Partner::firstOrFail()->account_number)->toBe('1234567890');
});

test('an e-wallet account is accepted, including a leading plus', function () {
    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillPartnerForm($form, [
            'payment_method' => 'e_wallet',
            'account_name' => 'Siti Rahmawati',
            'account_number' => '+6281234567890',
        ]))
        ->call('savePartner')
        ->assertHasNoErrors();

    $partner = Partner::firstOrFail();

    expect($partner->payment_method)->toBe('e_wallet')
        ->and($partner->paymentMethodLabel())->toBe('E-wallet')
        // The provider is optional: the payment data is complete without it.
        ->and($partner->payment_provider)->toBeNull()
        ->and($partner->account_number)->toBe('+6281234567890');
});

test('the payment provider is free text: it is trimmed, and any name is accepted', function () {
    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillPartnerForm($form, [
            'payment_method' => 'bank',
            'payment_provider' => '  Koperasi Simpan Pinjam Mawar  ',
            'account_name' => 'Siti Rahmawati',
            'account_number' => '1234567890',
        ]))
        ->call('savePartner')
        ->assertHasNoErrors();

    expect(Partner::firstOrFail()->payment_provider)->toBe('Koperasi Simpan Pinjam Mawar');
});

test('the payment provider is not tied to the payment method', function () {
    $this->actingAs(partnerAdmin());

    // Nothing checks the provider against a list, or against the method.
    foreach ([['bank', 'GoPay'], ['e_wallet', 'BCA']] as $index => [$method, $provider]) {
        Livewire::test('partner.partner-form-modal')
            ->call('openForm')
            ->tap(fn ($form) => fillPartnerForm($form, [
                'name' => "Mitra {$index}",
                'payment_method' => $method,
                'payment_provider' => $provider,
                'account_name' => 'Siti Rahmawati',
                'account_number' => '1234567890',
            ]))
            ->call('savePartner')
            ->assertHasNoErrors();
    }

    expect(Partner::pluck('payment_provider')->sort()->values()->all())->toBe(['BCA', 'GoPay']);
});

test('the payment provider is limited to 100 characters', function () {
    $this->actingAs(partnerAdmin());

    $payment = ['payment_method' => 'bank', 'account_name' => 'Siti', 'account_number' => '1234567890'];

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillPartnerForm($form, $payment + ['payment_provider' => str_repeat('a', 101)]))
        ->call('savePartner')
        ->assertHasErrors(['payment_provider' => 'max']);

    expect(Partner::count())->toBe(0);

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillPartnerForm($form, $payment + ['payment_provider' => str_repeat('a', 100)]))
        ->call('savePartner')
        ->assertHasNoErrors();

    expect(Partner::firstOrFail()->payment_provider)->toBe(str_repeat('a', 100));
});

test('only the account number is encrypted: the provider stays plain text', function () {
    $partner = Partner::factory()->withProvider('BCA')->create(['account_number' => '1234567890']);

    $raw = DB::table('partners')->where('id', $partner->id)->first();

    expect($raw->payment_provider)->toBe('BCA')
        ->and($raw->account_number)->not->toContain('1234567890');
});

test('payment_provider is a nullable column', function () {
    $column = collect(Schema::getColumns('partners'))->firstWhere('name', 'payment_provider');

    expect($column)->not->toBeNull()
        ->and($column['nullable'])->toBeTrue()
        ->and(Partner::factory()->create()->fresh()->payment_provider)->toBeNull();
});

test('the payment methods are bank and e-wallet', function () {
    expect(array_keys(Partner::PAYMENT_METHODS))->toBe(['bank', 'e_wallet']);
});

/*
|--------------------------------------------------------------------------
| The account number is encrypted
|--------------------------------------------------------------------------
*/

test('the account number is stored encrypted, not as plain text', function () {
    $partner = Partner::factory()->create(['account_number' => '1234567890']);

    $raw = DB::table('partners')->where('id', $partner->id)->value('account_number');

    expect($raw)->not->toBe('1234567890')
        ->and($raw)->not->toContain('1234567890')
        // It is the application's own encryption, not just an obscured string.
        ->and(Crypt::decryptString($raw))->toBe('1234567890')
        ->and($partner->fresh()->account_number)->toBe('1234567890');
});

test('the same account number encrypts differently every time', function () {
    $first = Partner::factory()->create(['account_number' => '1234567890']);
    $second = Partner::factory()->create(['account_number' => '1234567890']);

    $raw = DB::table('partners')->whereIn('id', [$first->id, $second->id])->pluck('account_number');

    expect($raw)->toHaveCount(2)->and($raw[0])->not->toBe($raw[1]);
});

test('a long account number fits the column', function () {
    $number = str_repeat('9', 30);
    $partner = Partner::factory()->create(['account_number' => $number]);

    expect($partner->fresh()->account_number)->toBe($number);
});

test('the account number is hidden from serialization', function () {
    $partner = Partner::factory()->create(['account_number' => '1234567890']);

    expect($partner->toArray())->not->toHaveKey('account_number')
        ->and($partner->toJson())->not->toContain('1234567890')
        ->and($partner->toJson())->not->toContain('account_number');
});

test('the account number is masked to its last four digits', function () {
    expect(Partner::factory()->make(['account_number' => '1234567890'])->maskedAccountNumber())->toBe('•••• 7890')
        ->and(Partner::factory()->make(['account_number' => '1234'])->maskedAccountNumber())->toBe('••••')
        ->and(Partner::factory()->withoutPayment()->make()->maskedAccountNumber())->toBeNull();
});

test('the list shows the masked account number and never the full one', function () {
    $partner = Partner::factory()->create([
        'name' => 'Rina Wijaya Unik',
        'account_number' => '9988776655',
    ]);

    $this->actingAs(partnerAdmin());

    Livewire::test('pages::partner.index')
        ->assertSeeHtml(partnerRow($partner))
        ->assertSeeInOrder(['Rina Wijaya Unik', '•••• 6655'])
        // Not in the page, and not in the component snapshot sent to the browser either.
        ->assertDontSee('9988776655', true, false)
        ->assertDontSee('998877', true, false);
});

test('the account number cannot be searched', function () {
    Partner::factory()->create(['account_number' => '9988776655']);

    $this->actingAs(partnerAdmin());

    // A hit would confirm that a number exists; encrypted values also could not match anyway.
    Livewire::test('pages::partner.index')
        ->set('search', '9988776655')
        ->assertSee('Belum ada data mitra.');
});

test('a stored number that can no longer be decrypted does not break the page', function () {
    $partner = Partner::factory()->create(['name' => 'Kunci Berubah', 'account_number' => '1234567890']);
    DB::table('partners')->where('id', $partner->id)->update(['account_number' => 'not-a-valid-payload']);

    $partner = $partner->fresh();

    expect($partner->accountNumberOrNull())->toBeNull()
        ->and($partner->accountNumberIsUnreadable())->toBeTrue()
        ->and($partner->maskedAccountNumber())->toBeNull();

    $this->actingAs(partnerAdmin());

    Livewire::test('pages::partner.index')
        ->assertOk()
        ->assertSeeHtml(partnerRow($partner))
        ->assertSee('Nomor tidak terbaca');
});

test('the edit form tells the admin when the stored number is unreadable and requires it again', function () {
    $partner = Partner::factory()->create(['account_number' => '1234567890']);
    DB::table('partners')->where('id', $partner->id)->update(['account_number' => 'not-a-valid-payload']);

    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->assertSet('account_number', '')
        ->assertSet('accountNumberUnreadable', true)
        ->assertSee('tidak dapat dibaca lagi')
        // The payment method is still set, so the number cannot be left empty.
        ->call('savePartner')
        ->assertHasErrors(['account_number' => 'required_with'])
        ->set('account_number', '5566778899')
        ->call('savePartner')
        ->assertHasNoErrors()
        // A failed save would also leave no validation error, so check that it really went through.
        ->assertDispatched('partner-saved')
        ->assertSet('isAddEditOpen', false);

    expect($partner->fresh())
        ->account_number->toBe('5566778899')
        ->and($partner->fresh()->accountNumberIsUnreadable())->toBeFalse();
});

test('a partner with an unreadable number can still be deactivated', function () {
    $partner = Partner::factory()->create(['account_number' => '1234567890']);
    DB::table('partners')->where('id', $partner->id)->update(['account_number' => 'not-a-valid-payload']);

    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-deactivate-modal')
        ->call('openDeactivate', $partner->id, $partner->name)
        ->call('deactivatePartner')
        ->assertDispatched('partner-deactivated');

    expect($partner->fresh()->status)->toBe('inactive');
});

test('clearing the payment details of a partner with an unreadable number removes the unreadable value', function () {
    $partner = Partner::factory()->create(['account_number' => '1234567890']);
    DB::table('partners')->where('id', $partner->id)->update(['account_number' => 'not-a-valid-payload']);

    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->set('payment_method', '')
        ->set('account_name', '')
        ->call('savePartner')
        ->assertHasNoErrors()
        ->assertDispatched('partner-saved');

    $raw = DB::table('partners')->where('id', $partner->id)->first();

    expect($raw->payment_method)->toBeNull()
        ->and($raw->account_name)->toBeNull()
        ->and($raw->account_number)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| user_id is reserved: nothing uses it yet
|--------------------------------------------------------------------------
*/

test('user_id is a nullable, unique column', function () {
    $user = User::factory()->create();

    expect(Schema::hasColumn('partners', 'user_id'))->toBeTrue();

    // Many partners without a login are fine...
    Partner::factory()->count(2)->create(['user_id' => null]);
    expect(Partner::whereNull('user_id')->count())->toBe(2);

    // ...but one user can belong to at most one partner.
    Partner::factory()->create(['user_id' => $user->id]);

    expect(fn () => Partner::factory()->create(['user_id' => $user->id]))
        ->toThrow(QueryException::class);
});

test('user_id is not mass assignable', function () {
    expect((new Partner)->isFillable('user_id'))->toBeFalse();

    $user = User::factory()->create();
    $partner = Partner::create(['name' => 'Coba', 'phone' => '0811', 'city' => 'Bandung', 'user_id' => $user->id]);

    expect($partner->fresh()->user_id)->toBeNull();
});

test('creating and editing through the form never touches user_id', function () {
    $user = User::factory()->create();
    $linked = Partner::factory()->create(['user_id' => $user->id]);

    $this->actingAs(partnerAdmin());

    // A partner created in the form has none...
    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillPartnerForm($form, ['name' => 'Baru']))
        ->call('savePartner')
        ->assertHasNoErrors();

    expect(Partner::where('name', 'Baru')->firstOrFail()->user_id)->toBeNull();

    // ...and editing one that already has a link leaves the link alone.
    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $linked->id)
        ->set('notes', 'Catatan baru')
        ->call('savePartner')
        ->assertHasNoErrors();

    expect($linked->fresh())->notes->toBe('Catatan baru')->user_id->toBe($user->id);
});

/*
|--------------------------------------------------------------------------
| Edit
|--------------------------------------------------------------------------
*/

test('admin can edit a partner through the form modal', function () {
    $partner = Partner::factory()->create(['name' => 'Nama Lama', 'city' => 'Jakarta']);

    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->assertSet('partnerId', $partner->id)
        ->assertSet('name', 'Nama Lama')
        ->set('name', 'Nama Baru')
        ->set('city', 'Bandung')
        ->set('phone', '089999999999')
        ->call('savePartner')
        ->assertHasNoErrors();

    expect($partner->fresh())
        ->name->toBe('Nama Baru')
        ->city->toBe('Bandung')
        ->phone->toBe('089999999999');
    expect(Partner::count())->toBe(1);
});

test('the edit form loads the full account number for someone who may edit', function () {
    $partner = Partner::factory()->create(['account_number' => '1234567890']);

    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->assertSet('account_number', '1234567890')
        ->assertSet('accountNumberUnreadable', false);
});

test('saving without touching the account number keeps it', function () {
    $partner = Partner::factory()->create(['account_number' => '1234567890']);

    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->set('notes', 'Hanya catatan')
        ->call('savePartner')
        ->assertHasNoErrors();

    expect($partner->fresh()->account_number)->toBe('1234567890');
});

test('the payment details can be changed', function () {
    $partner = Partner::factory()->create(['payment_method' => 'bank', 'account_number' => '1234567890']);

    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->set('payment_method', 'e_wallet')
        ->set('account_number', '0899887766')
        ->call('savePartner')
        ->assertHasNoErrors();

    expect($partner->fresh())->payment_method->toBe('e_wallet')->account_number->toBe('0899887766');
});

test('the edit form loads the payment provider and can change it', function () {
    $partner = Partner::factory()->withProvider('BCA')->create();

    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->assertSet('payment_provider', 'BCA')
        ->set('payment_provider', 'Mandiri')
        ->call('savePartner')
        ->assertHasNoErrors()
        ->assertDispatched('partner-saved');

    expect($partner->fresh()->payment_provider)->toBe('Mandiri');
});

test('clearing the payment details also clears the provider', function () {
    $partner = Partner::factory()->withProvider('BCA')->create();

    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->set('payment_method', '')
        ->set('payment_provider', '')
        ->set('account_name', '')
        ->set('account_number', '')
        ->call('savePartner')
        ->assertHasNoErrors()
        ->assertDispatched('partner-saved');

    $raw = DB::table('partners')->where('id', $partner->id)->first();

    expect($raw->payment_method)->toBeNull()
        ->and($raw->payment_provider)->toBeNull()
        ->and($raw->account_name)->toBeNull()
        ->and($raw->account_number)->toBeNull();
});

test('a provider left behind after clearing the other payment fields is refused', function () {
    $partner = Partner::factory()->withProvider('BCA')->create();

    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->set('payment_method', '')
        ->set('account_name', '')
        ->set('account_number', '')
        ->call('savePartner')
        ->assertHasErrors(['payment_method' => 'required_with']);

    expect($partner->fresh()->payment_provider)->toBe('BCA');
});

test('clearing all three payment fields removes the payment details', function () {
    $partner = Partner::factory()->create();

    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->set('payment_method', '')
        ->set('account_name', '')
        ->set('account_number', '')
        ->call('savePartner')
        ->assertHasNoErrors();

    $raw = DB::table('partners')->where('id', $partner->id)->first();

    expect($raw->payment_method)->toBeNull()
        ->and($raw->account_name)->toBeNull()
        ->and($raw->account_number)->toBeNull();
});

test('admin can reactivate an inactive partner through the edit form', function () {
    $partner = Partner::factory()->inactive()->create();

    $this->actingAs(partnerAdmin());

    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->assertSet('status', 'inactive')
        ->set('status', 'active')
        ->call('savePartner')
        ->assertHasNoErrors();

    expect($partner->fresh()->status)->toBe('active');
});

/*
|--------------------------------------------------------------------------
| Deactivate (partners are never hard-deleted)
|--------------------------------------------------------------------------
*/

test('admin can deactivate a partner through the deactivate modal', function () {
    $admin = partnerAdmin();
    $partner = Partner::factory()->create();
    $other = Partner::factory()->create();

    $this->actingAs($admin);

    Livewire::test('partner.partner-deactivate-modal')
        ->call('openDeactivate', $partner->id, $partner->name)
        ->call('deactivatePartner')
        ->assertHasNoErrors();

    // Status flips to inactive...
    expect($partner->fresh()->status)->toBe('inactive');
    $this->assertDatabaseHas('partners', ['id' => $partner->id, 'status' => 'inactive']);
    // ...the row is still in the database (deactivated, not deleted)...
    expect(Partner::find($partner->id))->not->toBeNull();
    expect(Partner::count())->toBe(2);
    // ...and nothing else was touched.
    expect($other->fresh()->status)->toBe('active');

    // A user without partner:partner-delete cannot deactivate.
    $plainUser = User::factory()->create();
    $plainUser->assignRole(config('alh.default_role'));

    $this->actingAs($plainUser);

    Livewire::test('partner.partner-deactivate-modal')
        ->call('openDeactivate', $other->id, $other->name)
        ->call('deactivatePartner')
        ->assertForbidden();

    expect($other->fresh()->status)->toBe('active');
});

test('the partner page still lists deactivated partners', function () {
    $partner = Partner::factory()->inactive()->create();

    $this->actingAs(partnerAdmin());

    Livewire::test('pages::partner.index')
        ->assertSeeHtml(partnerRow($partner));
});

test('the partners table has no soft delete column', function () {
    expect(Schema::hasColumn('partners', 'deleted_at'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Access without permission (per action)
|--------------------------------------------------------------------------
*/

test('a role without partner:partner-create cannot call savePartner directly', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    $this->actingAs($user);

    Livewire::test('partner.partner-form-modal')
        ->tap(fn ($form) => fillPartnerForm($form, ['name' => 'Tanpa Izin']))
        ->call('savePartner')
        ->assertForbidden();

    expect(Partner::where('name', 'Tanpa Izin')->exists())->toBeFalse();
});

test('a role without partner:partner-delete cannot call deactivatePartner directly', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));
    $partner = Partner::factory()->create();

    $this->actingAs($user);

    Livewire::test('partner.partner-deactivate-modal')
        ->call('openDeactivate', $partner->id, $partner->name)
        ->call('deactivatePartner')
        ->assertForbidden();

    expect(Partner::find($partner->id))->not->toBeNull();
    expect($partner->fresh()->status)->toBe('active');
});

test('a role without create or edit cannot open the partner form', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));
    $partner = Partner::factory()->create();

    $this->actingAs($user);

    Livewire::test('partner.partner-form-modal')
        ->call('openForm')
        ->assertForbidden();

    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->assertForbidden();
});

test('a role that can only view never receives the full account number', function () {
    // Not the number used as an example in the form's placeholder, which is on the page too.
    $partner = Partner::factory()->create(['account_number' => '4455667788']);

    $this->actingAs(partnerUserWith(['partner:partner-view']));

    // The list is masked...
    Livewire::test('pages::partner.index')
        ->assertSee('•••• 7788')
        ->assertDontSee('4455667788', true, false);

    // ...and the form that would load the full number refuses to open.
    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->assertForbidden()
        ->assertSet('account_number', '');
});

test('a role with only partner:partner-create cannot edit an existing partner', function () {
    $partner = Partner::factory()->create(['name' => 'Nama Asli']);

    $this->actingAs(partnerUserWith(['partner:partner-view', 'partner:partner-create']));

    // The edit target is a public property, so a client can set it directly
    // instead of going through openForm().
    Livewire::test('partner.partner-form-modal')
        ->set('partnerId', $partner->id)
        ->tap(fn ($form) => fillPartnerForm($form, ['name' => 'Diubah Paksa']))
        ->call('savePartner')
        ->assertForbidden();

    expect($partner->fresh()->name)->toBe('Nama Asli');
});

test('a role with only partner:partner-edit cannot create a partner', function () {
    $this->actingAs(partnerUserWith(['partner:partner-view', 'partner:partner-edit']));

    Livewire::test('partner.partner-form-modal')
        ->tap(fn ($form) => fillPartnerForm($form, ['name' => 'Mitra Baru']))
        ->call('savePartner')
        ->assertForbidden();

    expect(Partner::count())->toBe(0);
});

test('a role with edit but not delete cannot deactivate a partner through the form', function () {
    $partner = Partner::factory()->create(['name' => 'Mitra Aktif']);

    $this->actingAs(partnerUserWith(['partner:partner-view', 'partner:partner-edit']));

    // Other edits are fine...
    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->set('name', 'Mitra Aktif Baru')
        ->call('savePartner')
        ->assertHasNoErrors();

    expect($partner->fresh())->name->toBe('Mitra Aktif Baru')->status->toBe('active');

    // ...but flipping the status to inactive is the same act as deactivating.
    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->set('status', 'inactive')
        ->call('savePartner')
        ->assertForbidden();

    expect($partner->fresh()->status)->toBe('active');

    // And so is the deactivate modal.
    Livewire::test('partner.partner-deactivate-modal')
        ->call('openDeactivate', $partner->id, $partner->name)
        ->call('deactivatePartner')
        ->assertForbidden();

    expect($partner->fresh()->status)->toBe('active');
});

test('a role with edit but not delete can still reactivate a partner', function () {
    $partner = Partner::factory()->inactive()->create();

    $this->actingAs(partnerUserWith(['partner:partner-view', 'partner:partner-edit']));

    Livewire::test('partner.partner-form-modal')
        ->call('openForm', $partner->id)
        ->set('status', 'active')
        ->call('savePartner')
        ->assertHasNoErrors();

    expect($partner->fresh()->status)->toBe('active');
});

test('a role with delete can deactivate a partner', function () {
    $partner = Partner::factory()->create();

    $this->actingAs(partnerUserWith(['partner:partner-view', 'partner:partner-delete']));

    Livewire::test('partner.partner-deactivate-modal')
        ->call('openDeactivate', $partner->id, $partner->name)
        ->call('deactivatePartner')
        ->assertHasNoErrors();

    expect($partner->fresh()->status)->toBe('inactive');
});

/*
|--------------------------------------------------------------------------
| List: search, filters, sorting
|--------------------------------------------------------------------------
*/

test('the partner list shows contact, location and payment method of each partner', function () {
    // Values that appear nowhere else on the page (placeholders, dropdowns), so that
    // assertSeeInOrder() can only be satisfied by this partner's own row.
    $partner = Partner::factory()->create([
        'name' => 'Rina Wijaya Unik',
        'city' => 'Semarang',
        'area' => 'Simpang Lima',
        'phone' => '085599887766',
        'payment_method' => 'e_wallet',
        'payment_provider' => 'GoPay',
        'account_name' => 'Rina W',
        'account_number' => '085599887766',
    ]);

    $this->actingAs(partnerAdmin());

    Livewire::test('pages::partner.index')
        ->assertSeeHtml(partnerRow($partner))
        ->assertSeeInOrder([
            'Rina Wijaya Unik',
            'Semarang',
            'Simpang Lima',
            '085599887766',
            'E-wallet',
            'GoPay',
            'Rina W',
            '•••• 7766',
        ])
        // The provider is shown right next to the payment method. Checked on the visible text:
        // Livewire puts comment markers around @if blocks, so the raw HTML is not contiguous.
        ->assertSeeText('E-wallet · GoPay');
});

test('a partner without payment details says so', function () {
    Partner::factory()->withoutPayment()->create(['name' => 'Tanpa Rekening Unik']);

    $this->actingAs(partnerAdmin());

    Livewire::test('pages::partner.index')
        ->assertSeeInOrder(['Tanpa Rekening Unik', 'Belum diisi']);
});

test('partners can be searched by name, phone, city, area and account name', function () {
    $match = Partner::factory()->create([
        'name' => 'Rina Wijaya Unik', 'phone' => '085599887766', 'city' => 'Semarang',
        'area' => 'Simpang Lima', 'account_name' => 'Pemilik Rekening Unik',
    ]);
    $other = Partner::factory()->create([
        'name' => 'Budi Santoso', 'phone' => '081100223344', 'city' => 'Medan',
        'area' => 'Petisah', 'account_name' => 'Budi S',
    ]);

    $this->actingAs(partnerAdmin());

    foreach (['Wijaya', '0855998', 'Semarang', 'Simpang', 'Pemilik Rekening'] as $term) {
        Livewire::test('pages::partner.index')
            ->set('search', $term)
            ->assertSeeHtml(partnerRow($match))
            ->assertDontSeeHtml(partnerRow($other));
    }
});

test('partners can be filtered by city and status', function () {
    $active = Partner::factory()->create(['city' => 'Semarang']);
    $inactive = Partner::factory()->inactive()->create(['city' => 'Semarang']);
    $elsewhere = Partner::factory()->create(['city' => 'Medan']);

    $this->actingAs(partnerAdmin());

    Livewire::test('pages::partner.index')
        ->set('tempFilterCity', 'Semarang')
        ->call('applyFilters')
        ->assertSeeHtml(partnerRow($active))
        ->assertSeeHtml(partnerRow($inactive))
        ->assertDontSeeHtml(partnerRow($elsewhere))
        ->set('tempFilterStatus', 'inactive')
        ->call('applyFilters')
        ->assertSeeHtml(partnerRow($inactive))
        ->assertDontSeeHtml(partnerRow($active))
        ->call('resetFilters')
        ->assertSeeHtml(partnerRow($active))
        ->assertSeeHtml(partnerRow($inactive))
        ->assertSeeHtml(partnerRow($elsewhere));
});

test('a tampered sort column or direction falls back instead of erroring', function () {
    $partner = Partner::factory()->create();

    $this->actingAs(partnerAdmin());

    DB::enableQueryLog();

    Livewire::test('pages::partner.index')
        ->set('sortColumn', 'account_number')
        ->set('sortDirection', 'sideways')
        ->assertOk()
        ->assertSeeHtml(partnerRow($partner));

    // Sorting on the encrypted column must not be possible either: check the SQL that ran.
    $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");

    expect($sql)->not->toContain('order by "account_number"')
        ->and($sql)->not->toContain('sideways')
        ->and($sql)->toContain('order by "id" asc');
});

test('partners can be sorted by name', function () {
    $zed = Partner::factory()->create(['name' => 'Zed Terakhir']);
    $abe = Partner::factory()->create(['name' => 'Abe Pertama']);

    $this->actingAs(partnerAdmin());

    Livewire::test('pages::partner.index')
        ->call('sortBy', 'name')
        ->assertSeeHtmlInOrder([partnerRow($abe), partnerRow($zed)])
        ->call('sortBy', 'name')
        ->assertSeeHtmlInOrder([partnerRow($zed), partnerRow($abe)]);
});

/*
|--------------------------------------------------------------------------
| Seeded roles
|--------------------------------------------------------------------------
*/

test('the seeder gives admin every partner permission, and the default and mitra roles none', function () {
    $partnerPermissions = [
        'partner:partner-view',
        'partner:partner-create',
        'partner:partner-edit',
        'partner:partner-delete',
    ];

    $admin = Role::findByName(config('alh.super_admin_role'));
    $default = Role::findByName(config('alh.default_role'));
    $mitra = Role::findByName('mitra');

    expect($admin->permissions->pluck('name')->all())->toContain(...$partnerPermissions)
        ->and($default->permissions->pluck('name')->all())->not->toContain(...$partnerPermissions)
        ->and($mitra->permissions->pluck('name')->all())->not->toContain(...$partnerPermissions)
        ->and($mitra->users)->toHaveCount(0);
});
