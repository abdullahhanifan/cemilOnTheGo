<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Product;
use App\Models\Store;

new class extends Component {
  use WithFileUploads;

  public int $productId = 0; // 0 for create, > 0 for edit

  public string $store_id = '';
  public string $name = '';
  public string $variant = '';
  public string $sku = '';
  public string $unit = 'pcs';
  public string $buy_price = '';
  public string $sell_price = '';
  public string $notes = '';
  public string $status = 'active';
  public bool $buy_price_confirmed = false;

  /** The newly chosen photo (a Livewire temporary upload), not yet stored. */
  public $photo = null;
  public bool $remove_photo = false;

  public bool $isAddEditOpen = false;

  /**
   * Stores a new product can be added to. A deactivated store keeps its
   * existing products but takes no new ones.
   */
  #[Computed]
  public function activeStores()
  {
    return Store::query()
      ->where('status', 'active')
      ->orderBy('name')
      ->get(['id', 'name', 'city', 'area']);
  }

  /**
   * The product's store, shown read-only while editing.
   */
  #[Computed]
  public function selectedStore(): ?Store
  {
    return ctype_digit($this->store_id) ? Store::find((int) $this->store_id) : null;
  }

  /**
   * URL of the photo the product being edited already has, if any.
   */
  #[Computed]
  public function currentPhotoUrl(): ?string
  {
    return $this->productId > 0 ? Product::find($this->productId)?->photoUrl() : null;
  }

  private function photoMessages(): array
  {
    return [
      'photo.image' => 'Foto harus berupa gambar.',
      'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
      'photo.max' => 'Ukuran foto maksimal 2 MB.',
      'photo.dimensions' => 'Dimensi foto maksimal ' . Product::PHOTO_MAX_PIXELS . ' x ' . Product::PHOTO_MAX_PIXELS . ' piksel.',
      'photo.uploaded' => 'Foto gagal diunggah. Coba lagi dengan berkas yang lebih kecil.',
    ];
  }

  /**
   * Check the photo as soon as it is chosen, so a bad file is reported before
   * the form is submitted. saveProduct() validates it again.
   */
  public function updatedPhoto(): void
  {
    $this->validateOnly('photo', ['photo' => Product::photoRules()], $this->photoMessages(), ['photo' => 'Foto']);
  }

  /**
   * Store the chosen photo under a random name. Neither the name nor the
   * extension comes from the client: the name is random and the extension is
   * derived from the file's content.
   */
  private function storePhoto(): string
  {
    $name = Str::random(40) . '.' . Product::photoExtension($this->photo);

    return $this->photo->storeAs(Product::PHOTO_DIRECTORY, $name, Product::PHOTO_DISK);
  }

  /**
   * Opening the form is authorized too: the edit form loads a product's data
   * into component state, and event listeners are as reachable over the wire
   * as any other public method.
   */
  #[On('open-product-form')]
  public function openForm(?int $id = null): void
  {
    abort_unless(
      auth()->user()?->can($id ? 'product:product-edit' : 'product:product-create'),
      403
    );

    $this->resetForm();
    if ($id) {
      $product = Product::findOrFail($id);
      $this->productId = $product->id;
      $this->store_id = (string) $product->store_id;
      $this->name = $product->name;
      $this->variant = $product->variant;
      $this->sku = $product->sku ?? '';
      $this->unit = $product->unit;
      $this->buy_price = (string) $product->buy_price;
      $this->sell_price = (string) $product->sell_price;
      $this->notes = $product->notes ?? '';
      $this->status = $product->status;
    }
    $this->isAddEditOpen = true;
  }

  public function resetForm(): void
  {
    $this->productId = 0;
    $this->store_id = '';
    $this->name = '';
    $this->variant = '';
    $this->sku = '';
    $this->unit = 'pcs';
    $this->buy_price = '';
    $this->sell_price = '';
    $this->notes = '';
    $this->status = 'active';
    $this->buy_price_confirmed = false;
    $this->photo = null;
    $this->remove_photo = false;
    $this->resetValidation();
  }

  /**
   * Authorization happens here, on the action itself — not only on the page
   * that dispatches the "open-product-form" event. A Livewire component's
   * public methods are reachable directly over the wire, so route- or
   * mount()-level checks on the parent page do not protect this action.
   */
  public function saveProduct(): void
  {
    $isEdit = $this->productId > 0;

    abort_unless(
      auth()->user()?->can($isEdit ? 'product:product-edit' : 'product:product-create'),
      403
    );

    $product = $isEdit ? Product::findOrFail($this->productId) : null;

    // A product never changes store: store_id is a public property a client
    // can set, so while editing the product's own store is used and the
    // submitted value is ignored.
    $storeId = $product ? $product->store_id : (int) $this->store_id;

    $this->name = trim($this->name);
    $this->variant = trim($this->variant);
    $this->sku = trim($this->sku);
    $this->unit = trim($this->unit);

    $rules = [
      'name' => [
        'required', 'string', 'max:255',
        Rule::unique('products', 'name')
          ->where(fn ($query) => $query->where('store_id', $storeId)->where('variant', $this->variant))
          ->ignore($product?->id),
      ],
      'variant' => 'nullable|string|max:100',
      'sku' => 'nullable|string|max:100',
      'unit' => 'required|string|max:30',
      'buy_price' => 'required|integer|min:0|max:999999999',
      'sell_price' => 'required|integer|min:0|max:999999999',
      'notes' => 'nullable|string|max:2000',
      'status' => 'required|string|in:active,inactive',
      'photo' => Product::photoRules(),
    ];

    if (! $isEdit) {
      $rules['store_id'] = ['required', 'integer', Rule::exists('stores', 'id')->where('status', 'active')];
    }

    $validated = $this->validate($rules, [
      'required' => ':attribute wajib diisi.',
      'max' => ':attribute maksimal :max karakter.',
      'in' => ':attribute tidak valid.',
      'integer' => ':attribute harus berupa angka bulat.',
      'min' => ':attribute tidak boleh negatif.',
      'store_id.required' => 'Pilih toko terlebih dahulu.',
      'store_id.integer' => 'Toko tidak valid atau sudah nonaktif.',
      'store_id.exists' => 'Toko tidak valid atau sudah nonaktif.',
      'name.unique' => 'Produk dengan nama dan varian yang sama sudah ada di toko ini.',
      'buy_price.max' => 'Harga beli maksimal Rp 999.999.999.',
      'sell_price.max' => 'Harga jual maksimal Rp 999.999.999.',
    ] + $this->photoMessages(), [
      'store_id' => 'Toko',
      'name' => 'Nama produk',
      'variant' => 'Varian',
      'sku' => 'SKU',
      'unit' => 'Satuan',
      'buy_price' => 'Harga beli',
      'sell_price' => 'Harga jual',
      'notes' => 'Catatan',
      'status' => 'Status',
      'photo' => 'Foto',
    ]);

    $buyPrice = (int) $validated['buy_price'];

    $data = [
      'name' => $validated['name'],
      // Blank string, not null: the (store, name, variant) unique key relies on it.
      'variant' => $validated['variant'] ?? '',
      'sku' => filled($validated['sku'] ?? null) ? $validated['sku'] : null,
      'unit' => $validated['unit'],
      'buy_price' => $buyPrice,
      'sell_price' => (int) $validated['sell_price'],
      'notes' => filled($validated['notes'] ?? null) ? $validated['notes'] : null,
      'status' => $validated['status'],
    ];

    // The price is "checked" when the product is created, when the buy price
    // changes, or when the admin confirms it is still right.
    if ($product === null || $buyPrice !== $product->buy_price || $this->buy_price_confirmed) {
      $data['buy_price_checked_at'] = now();
    }

    // Deactivating needs the delete permission whether it is done from the
    // deactivate modal or by flipping the status here. Reactivating only
    // needs edit. This must stay outside the try block below: it would
    // swallow the 403 and turn it into a notification.
    abort_if(
      $product?->status === 'active'
        && $data['status'] === 'inactive'
        && ! auth()->user()?->can('product:product-delete'),
      403
    );

    $oldPhotoPath = $product?->photo_path;
    $newPhotoPath = null;

    try {
      if ($this->photo) {
        $newPhotoPath = $this->storePhoto();
        $data['photo_path'] = $newPhotoPath;
      } elseif ($product && $this->remove_photo) {
        $data['photo_path'] = null;
      }

      if ($product) {
        $product->update($data);
      } else {
        Product::create($data + ['store_id' => $storeId]);
      }
    } catch (\Throwable $e) {
      // Nothing points at the new file, so it must not be left behind.
      if ($newPhotoPath) {
        Storage::disk(Product::PHOTO_DISK)->delete($newPhotoPath);
      }

      report($e);
      $this->isAddEditOpen = false;
      $msg = 'Gagal menyimpan produk. Silakan coba lagi.';
      session()->flash('error', $msg);
      $this->dispatch('notify', type: 'error', message: $msg);

      return;
    }

    // Saved. Only now drop the file that was replaced or removed; failing to
    // delete it must not undo, or be reported as, a save that succeeded.
    if ($oldPhotoPath && array_key_exists('photo_path', $data) && $data['photo_path'] !== $oldPhotoPath) {
      try {
        Storage::disk(Product::PHOTO_DISK)->delete($oldPhotoPath);
      } catch (\Throwable $e) {
        report($e);
      }
    }

    $this->isAddEditOpen = false;
    $this->resetForm();
    $this->dispatch('product-saved');
    $msg = $isEdit ? 'Produk berhasil diperbarui!' : 'Produk berhasil ditambahkan!';
    session()->flash('success', $msg);
    $this->dispatch('notify', type: 'success', message: $msg);
  }
};
?>

<div>
  @php
    // Live preview of the derived margin; it is never stored.
    $canPreview = ctype_digit($buy_price) && ctype_digit($sell_price) && strlen($buy_price) <= 10 && strlen($sell_price) <= 10;
    $previewMargin = $canPreview ? (int) $sell_price - (int) $buy_price : null;
    $previewPercent = $canPreview && (int) $sell_price > 0 ? round($previewMargin / (int) $sell_price * 100, 1) : null;
  @endphp

  <div x-data="{ show: @entangle('isAddEditOpen') }" x-show="show" x-cloak
    class="z-99999 fixed inset-0 flex justify-center overflow-y-auto p-5">
    <div @click="show = false" class="fixed inset-0 h-full w-full bg-gray-900/50 backdrop-blur-sm"></div>

    <div
      class="border-gray-150 relative my-auto w-full max-w-xl rounded-[20px] border bg-white p-6 font-sans shadow-xl transition dark:border-gray-800 dark:bg-gray-950">
      <div class="mb-5 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800">
        <h4 class="text-xl font-bold text-gray-800 dark:text-white">
          {{ $productId > 0 ? 'Edit Produk' : 'Tambah Produk' }}
        </h4>
        <button @click="show = false" class="text-gray-400 transition hover:text-gray-600 dark:hover:text-white">
          <x-svg.x class="h-6 w-6" />
        </button>
      </div>

      <form wire:submit.prevent="saveProduct" class="space-y-4">
        <!-- Store -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Toko</label>
          @if ($productId > 0)
            <div
              class="flex h-11 w-full items-center rounded-lg border border-gray-200 bg-gray-50 px-4 text-sm text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
              {{ $this->selectedStore?->name }}
              @if ($this->selectedStore?->city)
                <span class="ml-1 text-gray-400">· {{ $this->selectedStore->city }}</span>
              @endif
              @if ($this->selectedStore?->status === 'inactive')
                <span class="ml-2 text-xs text-gray-400">(nonaktif)</span>
              @endif
            </div>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
              Toko tidak bisa diubah. Untuk memindahkan produk, nonaktifkan lalu buat produk baru di toko tujuan.
            </p>
          @else
            <div class="relative">
              <select wire:model="store_id"
                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full appearance-none rounded-lg border bg-transparent pl-4 pr-11 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('store_id') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror">
                <option value="">Pilih toko</option>
                @foreach ($isAddEditOpen ? $this->activeStores : [] as $store)
                  <option value="{{ $store->id }}">{{ $store->name }} — {{ $store->city }}@if ($store->area), {{ $store->area }}@endif</option>
                @endforeach
              </select>
              <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                <x-svg.chevron-down class="h-4 w-4" />
              </span>
            </div>
            @error('store_id')
            <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
            @enderror
          @endif
        </div>

        <!-- Name -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Produk</label>
          <input type="text" wire:model="name" placeholder="e.g. Croissant Almond"
            class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('name') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
          @error('name')
          <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
          @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
          <!-- Variant -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Varian <span class="font-normal text-gray-400">(opsional)</span></label>
            <input type="text" wire:model="variant" placeholder="e.g. Isi 6, Rasa Cokelat"
              class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('variant') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
            @error('variant')
            <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
            @enderror
          </div>

          <!-- SKU -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Kode dari Toko <span class="font-normal text-gray-400">(opsional)</span></label>
            <input type="text" wire:model="sku" placeholder="e.g. CRS-006"
              class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
          </div>
        </div>

        <!-- Unit -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Satuan</label>
          <input type="text" wire:model="unit" list="product-units" placeholder="e.g. pcs, box, kg"
            class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('unit') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
          <datalist id="product-units">
            @foreach (['pcs', 'box', 'pack', 'kg', 'gram', 'liter', 'botol', 'lusin'] as $unitOption)
              <option value="{{ $unitOption }}"></option>
            @endforeach
          </datalist>
          @error('unit')
          <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
          @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
          <!-- Buy price -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Harga Beli di Toko (Rp)</label>
            <input type="number" min="0" step="1" inputmode="numeric" wire:model.live.debounce.400ms="buy_price" placeholder="0"
              class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('buy_price') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
            @error('buy_price')
            <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
            @enderror
          </div>

          <!-- Sell price -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Harga Jual ke Customer (Rp)</label>
            <input type="number" min="0" step="1" inputmode="numeric" wire:model.live.debounce.400ms="sell_price" placeholder="0"
              class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('sell_price') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
            @error('sell_price')
            <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
            @enderror
          </div>
        </div>

        <!-- Estimated margin (derived, not stored) -->
        <div class="flex items-center justify-between rounded-lg border border-gray-100 bg-gray-50 px-4 py-2.5 dark:border-gray-800 dark:bg-white/[0.02]">
          <span class="text-sm text-gray-600 dark:text-gray-400">Estimasi margin</span>
          <span class="text-sm font-semibold {{ $previewMargin !== null && $previewMargin < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-white/90' }}">
            @if ($previewMargin !== null)
              {{ \App\Helpers\Rupiah::format($previewMargin) }}@if ($previewPercent !== null) ({{ number_format($previewPercent, 1, ',', '.') }}%)@endif
            @else
              -
            @endif
          </span>
        </div>

        @if ($productId > 0)
          <!-- Price re-check -->
          <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-400">
            <input type="checkbox" wire:model="buy_price_confirmed"
              class="mt-0.5 h-4 w-4 rounded border-gray-300 dark:border-gray-700" />
            <span>Harga beli sudah dicek ulang di toko (memperbarui waktu pengecekan, walau harganya tidak berubah)</span>
          </label>
        @endif

        <!-- Notes -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Catatan</label>
          <textarea wire:model="notes" rows="2" placeholder="Catatan tambahan (mis. stok terbatas, harga naik saat akhir pekan)"
            class="dark:bg-dark-900 shadow-theme-xs w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
        </div>

        <!-- Photo -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Foto Produk <span class="font-normal text-gray-400">(opsional)</span></label>
          <div class="flex items-start gap-4">
            <div
              class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03]">
              @if ($photo && ! $errors->has('photo') && $photo->isPreviewable())
                <img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau foto" class="h-full w-full object-cover" />
              @elseif ($this->currentPhotoUrl && ! $remove_photo)
                <img src="{{ $this->currentPhotoUrl }}" alt="Foto produk saat ini" class="h-full w-full object-cover" />
              @else
                <x-svg.product class="h-8 w-8 text-gray-300 dark:text-gray-600" />
              @endif
            </div>

            <div class="min-w-0 flex-1">
              <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp"
                class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200 dark:text-gray-400 dark:file:bg-white/5 dark:file:text-gray-300" />
              <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG, atau WebP. Maksimal 2 MB.</p>
              <p wire:loading wire:target="photo" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Mengunggah...</p>
              @error('photo')
              <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
              @enderror

              @if ($productId > 0 && $this->currentPhotoUrl && ! $photo)
                <label class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400">
                  <input type="checkbox" wire:model.live="remove_photo"
                    class="h-4 w-4 rounded border-gray-300 dark:border-gray-700" />
                  <span>Hapus foto ini</span>
                </label>
              @endif
            </div>
          </div>
        </div>

        <!-- Status -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Status</label>
          <div x-data="{ isOptionSelected: @entangle('status').live }" class="relative z-20 bg-transparent">
            <select wire:model="status"
              class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent bg-none pl-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900"
              :class="isOptionSelected && 'text-gray-800 dark:text-white/90'" @change="isOptionSelected = true">
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </select>
            <span class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
              <x-svg.chevron-down class="h-4 w-4" />
            </span>
          </div>
          @error('status')
          <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
          @enderror
        </div>

        <!-- Actions -->
        <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-100 pt-5 dark:border-gray-800">
          <button type="button" @click="show = false"
            class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5">
            Batal
          </button>
          <button type="submit" wire:loading.attr="disabled"
            class="flex items-center justify-center gap-2 rounded-xl bg-accent-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-accent-primary-hover">
            <x-ui.spinner.spinner-four :icon-only="true" class="w-5 h-5 text-white" wire:loading wire:target="saveProduct" />
            <span>Simpan Produk</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
