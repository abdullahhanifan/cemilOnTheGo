<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Vendor;

new class extends Component {
  public int $vendorId = 0; // 0 for create, > 0 for edit

  public string $name = '';
  public string $contact_name = '';
  public string $phone = '';
  public string $email = '';
  public string $address = '';
  public string $category = '';
  public string $status = 'active';

  public bool $isAddEditOpen = false;

  #[On('open-vendor-form')]
  public function openForm(?int $id = null): void
  {
    $this->resetForm();
    if ($id) {
      $vendor = Vendor::findOrFail($id);
      $this->vendorId = $vendor->id;
      $this->name = $vendor->name;
      $this->contact_name = $vendor->contact_name ?? '';
      $this->phone = $vendor->phone ?? '';
      $this->email = $vendor->email ?? '';
      $this->address = $vendor->address ?? '';
      $this->category = $vendor->category ?? '';
      $this->status = $vendor->status;
    }
    $this->isAddEditOpen = true;
  }

  public function resetForm(): void
  {
    $this->vendorId = 0;
    $this->name = '';
    $this->contact_name = '';
    $this->phone = '';
    $this->email = '';
    $this->address = '';
    $this->category = '';
    $this->status = 'active';
    $this->resetValidation();
  }

  /**
   * Authorization happens here, on the action itself — not only on the page
   * that dispatches the "open-vendor-form" event. A Livewire component's
   * public methods are reachable directly over the wire, so route- or
   * mount()-level checks on the parent page do not protect this action.
   */
  public function saveVendor(): void
  {
    $isEdit = $this->vendorId > 0;

    abort_unless(
      auth()->user()?->can($isEdit ? 'vendor:vendor-edit' : 'vendor:vendor-create'),
      403
    );

    $validated = $this->validate([
      'name' => 'required|string|max:255',
      'contact_name' => 'nullable|string|max:255',
      'phone' => 'nullable|string|max:50',
      'email' => 'nullable|email|max:255',
      'address' => 'nullable|string|max:1000',
      'category' => 'nullable|string|max:255',
      'status' => 'required|string|in:active,inactive',
    ]);

    try {
      if ($isEdit) {
        $vendor = Vendor::findOrFail($this->vendorId);
        $vendor->update($validated);
      } else {
        Vendor::create($validated);
      }

      $this->isAddEditOpen = false;
      $this->resetForm();
      $this->dispatch('vendor-saved');
      $msg = $isEdit ? 'Vendor berhasil diperbarui!' : 'Vendor berhasil ditambahkan!';
      session()->flash('success', $msg);
      $this->dispatch('notify', type: 'success', message: $msg);
    } catch (\Throwable $e) {
      $this->isAddEditOpen = false;
      $msg = 'Gagal menyimpan vendor: ' . $e->getMessage();
      session()->flash('error', $msg);
      $this->dispatch('notify', type: 'error', message: $msg);
    }
  }
};
?>

<div>
  <div x-data="{ show: @entangle('isAddEditOpen') }" x-show="show" x-cloak
    class="z-99999 fixed inset-0 flex items-center justify-center overflow-y-auto p-5">
    <div @click="show = false" class="fixed inset-0 h-full w-full bg-gray-900/50 backdrop-blur-sm"></div>

    <div
      class="border-gray-150 relative w-full max-w-xl rounded-[20px] border bg-white p-6 font-sans shadow-xl transition dark:border-gray-800 dark:bg-gray-950">
      <div class="mb-5 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800">
        <h4 class="text-xl font-bold text-gray-800 dark:text-white">
          {{ $vendorId > 0 ? 'Edit Vendor' : 'Tambah Vendor' }}
        </h4>
        <button @click="show = false" class="text-gray-400 transition hover:text-gray-600 dark:hover:text-white">
          <x-svg.x class="h-6 w-6" />
        </button>
      </div>

      <form wire:submit.prevent="saveVendor" class="space-y-4">
        <!-- Name -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Vendor</label>
          <input type="text" wire:model="name" placeholder="e.g. PT Sumber Makmur"
            class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('name') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
          @error('name')
          <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
          @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
          <!-- Contact Name -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Kontak (PIC)</label>
            <input type="text" wire:model="contact_name" placeholder="e.g. Budi Santoso"
              class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
          </div>

          <!-- Phone -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Telepon</label>
            <input type="text" wire:model="phone" placeholder="e.g. 0812xxxxxxx"
              class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
          </div>
        </div>

        <!-- Email -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email</label>
          <input type="email" wire:model="email" placeholder="vendor@example.test"
            class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('email') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
          @error('email')
          <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
          @enderror
        </div>

        <!-- Address -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Alamat</label>
          <textarea wire:model="address" rows="2" placeholder="Alamat lengkap vendor"
            class="dark:bg-dark-900 shadow-theme-xs w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <!-- Category -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Kategori</label>
            <input type="text" wire:model="category" placeholder="e.g. Jasa, Barang, Konsultan"
              class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
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
          </div>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-100 pt-5 dark:border-gray-800">
          <button type="button" @click="show = false"
            class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5">
            Batal
          </button>
          <button type="submit" wire:loading.attr="disabled"
            class="flex items-center justify-center gap-2 rounded-xl bg-accent-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-accent-primary-hover">
            <x-ui.spinner.spinner-four :icon-only="true" class="w-5 h-5 text-white" wire:loading wire:target="saveVendor" />
            <span>Simpan Vendor</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
