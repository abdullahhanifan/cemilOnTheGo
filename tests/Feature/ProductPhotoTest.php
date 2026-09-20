<?php

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    // Where the photo is finally stored. (Livewire itself parks the upload on its own scratch
    // disk while tests run, so nothing reaches the real storage/app.)
    Storage::fake('public');
});

function photoAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(config('alh.super_admin_role'));

    return $admin;
}

/**
 * A user whose only role carries exactly the given permissions.
 *
 * @param  list<string>  $permissions
 */
function photoUserWith(array $permissions): User
{
    $role = Role::create(['name' => 'photo-test-'.uniqid()]);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * A product that already has a stored photo.
 */
function productWithPhoto(): Product
{
    Storage::disk('public')->put('products/existing-photo.jpg', 'existing');

    return Product::factory()->create(['photo_path' => 'products/existing-photo.jpg']);
}

/**
 * Files on the public disk, as a sorted list of paths.
 *
 * @return list<string>
 */
function storedPhotos(): array
{
    $files = Storage::disk('public')->allFiles();
    sort($files);

    return $files;
}

/**
 * Fill the required product fields on a form that is already open for a new product.
 */
function fillNewProduct($form, string $name)
{
    return $form
        ->set('store_id', (string) Store::factory()->create()->id)
        ->set('name', $name)
        ->set('buy_price', '1000')
        ->set('sell_price', '2000');
}

/**
 * A Livewire test fake of an upload that must be refused, keyed by kind.
 */
function unsupportedUpload(string $kind): UploadedFile
{
    return match ($kind) {
        'pdf' => UploadedFile::fake()->create('brosur.pdf', 100, 'application/pdf'),
        'svg' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        'gif' => UploadedFile::fake()->image('animasi.gif'),
        'text' => UploadedFile::fake()->create('catatan.txt', 10, 'text/plain'),
        'php' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php'),
    };
}

/**
 * Bytes of a real file of the given kind (not a Livewire test fake).
 */
function fixtureBytes(string $kind): string
{
    $text = [
        'text' => 'ini bukan gambar sama sekali',
        'php' => '<?php system($_GET["c"]); ?>',
        'svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        'empty' => '',
    ];

    if (array_key_exists($kind, $text)) {
        return $text[$kind];
    }

    $image = imagecreatetruecolor(8, 8);

    ob_start();
    match ($kind) {
        'png' => imagepng($image),
        'jpeg' => imagejpeg($image),
        'gif' => imagegif($image),
        'webp' => imagewebp($image),
    };

    return ob_get_clean();
}

/**
 * A real file on disk, so its type is judged by its content the way it is in production.
 */
function realFile(string $kind, string $clientName): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'photo');
    file_put_contents($path, fixtureBytes($kind));

    return new UploadedFile($path, $clientName, null, null, true);
}

/*
|--------------------------------------------------------------------------
| Storing
|--------------------------------------------------------------------------
*/

test('a product is created with a photo stored on the public disk under a random name', function () {
    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillNewProduct($form, 'Croissant Foto'))
        ->set('photo', UploadedFile::fake()->image('foto-asli-croissant.jpg', 600, 400))
        ->assertHasNoErrors()
        ->call('saveProduct')
        ->assertHasNoErrors()
        ->assertSet('photo', null);

    $product = Product::where('name', 'Croissant Foto')->firstOrFail();

    expect($product->photo_path)->toMatch('/^products\/[A-Za-z0-9]{40}\.jpg$/')
        ->and($product->photoUrl())->toBe(asset('storage/'.$product->photo_path));
    Storage::disk('public')->assertExists($product->photo_path);
});

test('the stored file name never contains the name the client gave the file', function () {
    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillNewProduct($form, 'Nama Berkas'))
        ->set('photo', UploadedFile::fake()->image('../../nasi-goreng-spesial.jpg'))
        ->call('saveProduct')
        ->assertHasNoErrors();

    $path = Product::where('name', 'Nama Berkas')->firstOrFail()->photo_path;

    expect($path)->not->toContain('nasi')
        ->and($path)->not->toContain('goreng')
        ->and($path)->not->toContain('..')
        ->and(storedPhotos())->toBe([$path]);
});

test('two uploads with the same client file name get different stored names', function () {
    $this->actingAs(photoAdmin());

    $paths = [];
    foreach (['Produk Satu', 'Produk Dua'] as $name) {
        Livewire::test('product.product-form-modal')
            ->call('openForm')
            ->tap(fn ($form) => fillNewProduct($form, $name))
            ->set('photo', UploadedFile::fake()->image('foto.jpg'))
            ->call('saveProduct')
            ->assertHasNoErrors();

        $paths[] = Product::where('name', $name)->firstOrFail()->photo_path;
    }

    expect($paths[0])->not->toBe($paths[1])
        ->and(storedPhotos())->toHaveCount(2);
});

test('the photo is optional', function () {
    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillNewProduct($form, 'Tanpa Foto'))
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Tanpa Foto')->firstOrFail();

    expect($product->photo_path)->toBeNull()
        ->and($product->photoUrl())->toBeNull()
        ->and(storedPhotos())->toBe([]);
});

test('a form that fails validation stores no photo', function () {
    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('photo', UploadedFile::fake()->image('foto.jpg'))
        ->call('saveProduct')
        ->assertHasErrors(['store_id', 'name', 'buy_price', 'sell_price']);

    expect(storedPhotos())->toBe([])
        ->and(Product::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Type, size and dimensions (through the form)
|--------------------------------------------------------------------------
*/

test('unsupported photo types are rejected and never stored', function (string $kind) {
    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->tap(fn ($form) => fillNewProduct($form, 'Foto Salah'))
        ->set('photo', unsupportedUpload($kind))
        ->assertHasErrors('photo')
        // Saving still fails on the photo: choosing a bad file cannot be bypassed by pressing save.
        ->call('saveProduct')
        ->assertHasErrors('photo');

    expect(Product::count())->toBe(0)
        ->and(storedPhotos())->toBe([]);
})->with(['pdf', 'svg', 'gif', 'text', 'php']);

test('jpg, jpeg and png photos are accepted', function (string $name) {
    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('photo', UploadedFile::fake()->image($name))
        ->assertHasNoErrors('photo');
})->with(['foto.jpg', 'foto.jpeg', 'foto.png']);

test('a webp photo is accepted', function () {
    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('photo', UploadedFile::fake()->image('foto.webp'))
        ->assertHasNoErrors('photo');
})->skip(! function_exists('imagewebp'), 'GD was built without WebP support');

test('a photo of exactly 2 MB is accepted and anything larger is rejected', function () {
    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('photo', UploadedFile::fake()->image('pas.jpg')->size(2048))
        ->assertHasNoErrors('photo')
        ->set('photo', UploadedFile::fake()->image('besar.jpg')->size(2049))
        ->assertHasErrors(['photo' => 'max']);
});

test('a photo wider or taller than 6000 pixels is rejected', function () {
    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->set('photo', UploadedFile::fake()->image('pas.jpg', 6000, 10))
        ->assertHasNoErrors('photo')
        ->set('photo', UploadedFile::fake()->image('lebar.jpg', 6001, 10))
        ->assertHasErrors(['photo' => 'dimensions'])
        ->set('photo', UploadedFile::fake()->image('tinggi.jpg', 10, 6001))
        ->assertHasErrors(['photo' => 'dimensions']);
});

test('the photo limits are the documented ones', function () {
    expect(Product::PHOTO_MAX_KB)->toBe(2048)
        ->and(Product::PHOTO_EXTENSIONS)->toBe(['jpg', 'jpeg', 'png', 'webp'])
        ->and(Product::PHOTO_EXTENSIONS)->not->toContain('svg')
        ->and(Product::PHOTO_EXTENSIONS)->not->toContain('gif');
});

/*
|--------------------------------------------------------------------------
| Type judged by content, not by name (real files)
|
| Livewire's test harness reports a fake upload's type from its metadata, so
| these use real files on disk to exercise the same checks production runs.
|--------------------------------------------------------------------------
*/

test('the photo rules judge a file by its content, not its name', function (string $kind, string $clientName, bool $rejected) {
    $file = realFile($kind, $clientName);

    $fails = Validator::make(['photo' => $file], ['photo' => Product::photoRules()])->fails();

    @unlink($file->getPathname());

    expect($fails)->toBe($rejected);
})->with([
    'real png named .png' => ['png', 'foto.png', false],
    'real jpeg named .jpg' => ['jpeg', 'foto.jpg', false],
    'real png renamed to .jpg' => ['png', 'foto.jpg', false],
    'real jpeg renamed to .png' => ['jpeg', 'foto.png', false],
    'real gif renamed to .jpg' => ['gif', 'foto.jpg', true],
    'text renamed to .jpg' => ['text', 'foto.jpg', true],
    'php source renamed to .jpg' => ['php', 'foto.jpg', true],
    'php source with a double extension' => ['php', 'shell.php.jpg', true],
    'svg with a script renamed to .png' => ['svg', 'logo.png', true],
    'an empty file named .jpg' => ['empty', 'kosong.jpg', true],
]);

test('the stored extension comes from the content, not from the client name', function (string $kind, string $clientName, string $expected) {
    $file = realFile($kind, $clientName);

    $extension = Product::photoExtension($file);

    @unlink($file->getPathname());

    expect($extension)->toBe($expected);
})->with([
    'png content named .jpg' => ['png', 'gambar.jpg', 'png'],
    'jpeg content named .png' => ['jpeg', 'gambar.png', 'jpg'],
    'jpeg content named .jpeg' => ['jpeg', 'gambar.jpeg', 'jpg'],
]);

test('content that is not an accepted photo type has no stored extension', function (string $kind) {
    $file = realFile($kind, 'gambar.jpg');

    try {
        expect(fn () => Product::photoExtension($file))->toThrow(InvalidArgumentException::class);
    } finally {
        @unlink($file->getPathname());
    }
})->with(['gif', 'text', 'php', 'svg']);

test('a webp file is recognised by its content', function () {
    $file = realFile('webp', 'gambar.png');

    $extension = Product::photoExtension($file);
    $rejected = Validator::make(['photo' => $file], ['photo' => Product::photoRules()])->fails();

    @unlink($file->getPathname());

    expect($extension)->toBe('webp')->and($rejected)->toBeFalse();
})->skip(! function_exists('imagewebp'), 'GD was built without WebP support');

/*
|--------------------------------------------------------------------------
| Replace, remove, keep
|--------------------------------------------------------------------------
*/

test('choosing a new photo replaces the old file', function () {
    $product = productWithPhoto();
    $old = $product->photo_path;

    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->set('photo', UploadedFile::fake()->image('baru.png'))
        ->call('saveProduct')
        ->assertHasNoErrors();

    $new = $product->fresh()->photo_path;

    expect($new)->not->toBe($old)
        ->and($new)->toMatch('/^products\/[A-Za-z0-9]{40}\.png$/')
        ->and(storedPhotos())->toBe([$new]);
    Storage::disk('public')->assertMissing($old);
});

test('ticking "hapus foto" removes the photo and its file', function () {
    $product = productWithPhoto();
    $old = $product->photo_path;

    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->set('remove_photo', true)
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->fresh()->photo_path)->toBeNull()
        ->and(storedPhotos())->toBe([]);
    Storage::disk('public')->assertMissing($old);
});

test('a newly chosen photo wins over "hapus foto"', function () {
    $product = productWithPhoto();
    $old = $product->photo_path;

    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->set('remove_photo', true)
        ->set('photo', UploadedFile::fake()->image('baru.jpg'))
        ->call('saveProduct')
        ->assertHasNoErrors();

    $new = $product->fresh()->photo_path;

    expect($new)->not->toBeNull()->and($new)->not->toBe($old)
        ->and(storedPhotos())->toBe([$new]);
});

test('editing something else keeps the photo and its file', function () {
    $product = productWithPhoto();
    $old = $product->photo_path;

    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->set('notes', 'Catatan baru')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->fresh()->photo_path)->toBe($old)
        ->and(storedPhotos())->toBe([$old]);
});

test('deactivating a product keeps its photo', function () {
    $product = productWithPhoto();
    $old = $product->photo_path;

    $this->actingAs(photoAdmin());

    Livewire::test('product.product-deactivate-modal')
        ->call('openDeactivate', $product->id, $product->name)
        ->call('deactivateProduct')
        ->assertHasNoErrors();

    expect($product->fresh())->status->toBe('inactive')->photo_path->toBe($old)
        ->and(storedPhotos())->toBe([$old]);
});

/*
|--------------------------------------------------------------------------
| A failed save must not leave files behind
|--------------------------------------------------------------------------
*/

test('a failed create removes the newly stored photo', function () {
    $this->actingAs(photoAdmin());

    Exceptions::fake();
    Product::creating(fn () => throw new RuntimeException('boom'));

    try {
        Livewire::test('product.product-form-modal')
            ->call('openForm')
            ->tap(fn ($form) => fillNewProduct($form, 'Gagal Simpan'))
            ->set('photo', UploadedFile::fake()->image('foto.jpg'))
            ->call('saveProduct');
    } finally {
        Product::flushEventListeners();
    }

    expect(Product::count())->toBe(0)
        ->and(storedPhotos())->toBe([]);
    Exceptions::assertReported(RuntimeException::class);
});

test('a failed update removes the new photo and keeps the old one', function () {
    $product = productWithPhoto();
    $old = $product->photo_path;

    $this->actingAs(photoAdmin());

    Exceptions::fake();
    Product::updating(fn () => throw new RuntimeException('boom'));

    try {
        Livewire::test('product.product-form-modal')
            ->call('openForm', $product->id)
            ->set('photo', UploadedFile::fake()->image('baru.jpg'))
            ->call('saveProduct');
    } finally {
        Product::flushEventListeners();
    }

    expect($product->fresh()->photo_path)->toBe($old)
        ->and(storedPhotos())->toBe([$old]);
    Exceptions::assertReported(RuntimeException::class);
});

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

test('a role without product:product-create cannot create a product with a photo', function () {
    $user = User::factory()->create();
    $user->assignRole(config('alh.default_role'));

    $this->actingAs($user);

    Livewire::test('product.product-form-modal')
        ->tap(fn ($form) => fillNewProduct($form, 'Tanpa Izin'))
        ->set('photo', UploadedFile::fake()->image('foto.jpg'))
        ->call('saveProduct')
        ->assertForbidden();

    expect(Product::count())->toBe(0)
        ->and(storedPhotos())->toBe([]);
});

test('a role that can only view cannot change a photo', function () {
    $product = productWithPhoto();
    $old = $product->photo_path;

    $this->actingAs(photoUserWith(['product:product-view']));

    // The edit target is a public property, so a client can set it directly.
    Livewire::test('product.product-form-modal')
        ->set('productId', $product->id)
        ->set('name', 'Diubah Paksa')
        ->set('buy_price', '1')
        ->set('sell_price', '2')
        ->set('photo', UploadedFile::fake()->image('baru.jpg'))
        ->call('saveProduct')
        ->assertForbidden();

    expect($product->fresh()->photo_path)->toBe($old)
        ->and(storedPhotos())->toBe([$old]);
});

test('a role with product:product-edit can change a photo', function () {
    $product = productWithPhoto();

    $this->actingAs(photoUserWith(['product:product-view', 'product:product-edit']));

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->set('photo', UploadedFile::fake()->image('baru.jpg'))
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->fresh()->photo_path)->not->toBe('products/existing-photo.jpg');
});

/*
|--------------------------------------------------------------------------
| Display
|--------------------------------------------------------------------------
*/

test('the product list shows the photo of a product that has one', function () {
    $with = productWithPhoto();
    $without = Product::factory()->create();

    $this->actingAs(photoAdmin());

    Livewire::test('pages::product.index')
        ->assertSeeHtml('src="'.$with->photoUrl().'"')
        ->assertSeeHtml('alt="'.$with->name.'"')
        ->assertDontSeeHtml('alt="'.$without->name.'"');
});

test('the edit form previews the current photo, and hides it once it is marked for removal', function () {
    $product = productWithPhoto();

    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm', $product->id)
        ->assertSeeHtml('src="'.$product->photoUrl().'"')
        ->set('remove_photo', true)
        ->assertDontSeeHtml('src="'.$product->photoUrl().'"');
});

test('the create form offers no removal option', function () {
    $this->actingAs(photoAdmin());

    Livewire::test('product.product-form-modal')
        ->call('openForm')
        ->assertDontSee('Hapus foto ini');
});

test('a product photo path is stored, and nullable', function () {
    expect(Schema::hasColumn('products', 'photo_path'))->toBeTrue()
        ->and(Product::factory()->create()->photo_path)->toBeNull();
});
