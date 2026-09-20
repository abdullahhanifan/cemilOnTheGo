<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Validation\Rule;
use App\Models\Partner;

new class extends Component {
  public int $partnerId = 0; // 0 for create, > 0 for edit

  public string $name = '';
  public string $phone = '';
  public string $city = '';
  public string $area = '';
  public string $payment_method = '';
  public string $payment_provider = '';
  public string $account_name = '';
  public string $account_number = '';
  public string $notes = '';
  public string $status = 'active';

  /** True while editing a partner whose stored account number can no longer be decrypted. */
  public bool $accountNumberUnreadable = false;

  public bool $isAddEditOpen = false;

  /**
   * Opening the form is authorized too: the edit form loads a partner's data
   * (including the decrypted account number) into component state, and event
   * listeners are as reachable over the wire as any other public method.
   */
  #[On('open-partner-form')]
  public function openForm(?int $id = null): void
  {
    abort_unless(
      auth()->user()?->can($id ? 'partner:partner-edit' : 'partner:partner-create'),
      403
    );

    $this->resetForm();
    if ($id) {
      $partner = Partner::findOrFail($id);
      $this->partnerId = $partner->id;
      $this->name = $partner->name;
      $this->phone = $partner->phone;
      $this->city = $partner->city;
      $this->area = $partner->area ?? '';
      $this->payment_method = $partner->payment_method ?? '';
      $this->payment_provider = $partner->payment_provider ?? '';
      $this->account_name = $partner->account_name ?? '';
      $this->account_number = $partner->accountNumberOrNull() ?? '';
      $this->accountNumberUnreadable = $partner->accountNumberIsUnreadable();
      $this->notes = $partner->notes ?? '';
      $this->status = $partner->status;
    }
    $this->isAddEditOpen = true;
  }

  public function resetForm(): void
  {
    $this->partnerId = 0;
    $this->name = '';
    $this->phone = '';
    $this->city = '';
    $this->area = '';
    $this->payment_method = '';
    $this->payment_provider = '';
    $this->account_name = '';
    $this->account_number = '';
    $this->notes = '';
    $this->status = 'active';
    $this->accountNumberUnreadable = false;
    $this->resetValidation();
  }

  /**
   * Authorization happens here, on the action itself — not only on the page
   * that dispatches the "open-partner-form" event. A Livewire component's
   * public methods are reachable directly over the wire, so route- or
   * mount()-level checks on the parent page do not protect this action.
   *
   * `user_id` (reserved for a future partner login) is never read or written here.
   */
  public function savePartner(): void
  {
    $isEdit = $this->partnerId > 0;

    abort_unless(
      auth()->user()?->can($isEdit ? 'partner:partner-edit' : 'partner:partner-create'),
      403
    );

    // Account numbers are often typed with spaces or dashes ("1234-5678-90").
    $this->name = trim($this->name);
    $this->phone = trim($this->phone);
    $this->city = trim($this->city);
    $this->payment_provider = trim($this->payment_provider);
    $this->account_name = trim($this->account_name);
    $this->account_number = preg_replace('/[\s-]+/', '', trim($this->account_number));

    $validated = $this->validate([
      'name' => 'required|string|max:255',
      'phone' => 'required|string|max:50',
      'city' => 'required|string|max:100',
      'area' => 'nullable|string|max:255',
      // Method, account name and account number go together: all filled, or all empty. The
      // provider is free text and optional, but it makes no sense without the rest.
      'payment_method' => ['nullable', Rule::in(array_keys(Partner::PAYMENT_METHODS)), 'required_with:account_name,account_number,payment_provider'],
      'payment_provider' => 'nullable|string|max:100',
      'account_name' => 'nullable|string|max:255|required_with:payment_method,account_number,payment_provider',
      'account_number' => ['nullable', 'string', 'regex:/^\+?[0-9]{5,30}$/', 'required_with:payment_method,account_name,payment_provider'],
      'notes' => 'nullable|string|max:2000',
      'status' => 'required|string|in:active,inactive',
    ], [
      'required' => ':attribute wajib diisi.',
      'required_with' => ':attribute wajib diisi bila data pembayaran lainnya diisi. Kosongkan semua data pembayaran bila belum ada.',
      'max' => ':attribute maksimal :max karakter.',
      'in' => ':attribute tidak valid.',
      'account_number.regex' => 'Nomor rekening hanya boleh berisi angka (5 sampai 30 digit), boleh diawali +.',
    ], [
      'name' => 'Nama mitra',
      'phone' => 'Telepon',
      'city' => 'Kota',
      'area' => 'Area',
      'payment_method' => 'Metode pembayaran',
      'payment_provider' => 'Penyedia',
      'account_name' => 'Nama pemilik rekening',
      'account_number' => 'Nomor rekening',
      'notes' => 'Catatan',
      'status' => 'Status',
    ]);

    $data = collect($validated)
      ->map(fn ($value) => $value === '' ? null : $value)
      ->all();

    $partner = $isEdit ? Partner::findOrFail($this->partnerId) : null;

    // Deactivating needs the delete permission whether it is done from the
    // deactivate modal or by flipping the status here. Reactivating only
    // needs edit. This must stay outside the try block below: it would
    // swallow the 403 and turn it into a notification.
    abort_if(
      $partner?->status === 'active'
        && $data['status'] === 'inactive'
        && ! auth()->user()?->can('partner:partner-delete'),
      403
    );

    try {
      if ($partner) {
        // The number is being entered again (or cleared): the old, unreadable one would
        // make the update itself throw.
        $partner->discardUnreadableAccountNumber();
        $partner->update($data);
      } else {
        Partner::create($data);
      }

      $this->isAddEditOpen = false;
      $this->resetForm();
      $this->dispatch('partner-saved');
      $msg = $isEdit ? 'Mitra berhasil diperbarui!' : 'Mitra berhasil ditambahkan!';
      session()->flash('success', $msg);
      $this->dispatch('notify', type: 'success', message: $msg);
    } catch (\Throwable $e) {
      report($e);
      $this->isAddEditOpen = false;
      $msg = 'Gagal menyimpan mitra. Silakan coba lagi.';
      session()->flash('error', $msg);
      $this->dispatch('notify', type: 'error', message: $msg);
    }
  }
};
?>

<div>
  <div x-data="{ show: @entangle('isAddEditOpen') }" x-show="show" x-cloak
    class="z-99999 fixed inset-0 flex justify-center overflow-y-auto p-5">
    <div @click="show = false" class="fixed inset-0 h-full w-full bg-gray-900/50 backdrop-blur-sm"></div>

    <div
      class="border-gray-150 relative my-auto w-full max-w-xl rounded-[20px] border bg-white p-6 font-sans shadow-xl transition dark:border-gray-800 dark:bg-gray-950">
      <div class="mb-5 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800">
        <h4 class="text-xl font-bold text-gray-800 dark:text-white">
          {{ $partnerId > 0 ? 'Edit Mitra' : 'Tambah Mitra' }}
        </h4>
        <button @click="show = false" class="text-gray-400 transition hover:text-gray-600 dark:hover:text-white">
          <x-svg.x class="h-6 w-6" />
        </button>
      </div>

      <form wire:submit.prevent="savePartner" class="space-y-4" autocomplete="off">
        <!-- Name -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Mitra</label>
          <input type="text" wire:model="name" placeholder="e.g. Siti Rahmawati"
            class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('name') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
          @error('name')
          <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
          @enderror
        </div>

        <!-- Phone -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Telepon</label>
          <input type="text" wire:model="phone" placeholder="e.g. 0812xxxxxxx"
            class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('phone') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
          @error('phone')
          <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
          @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
          <!-- City -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Kota</label>
            <input type="text" wire:model="city" placeholder="e.g. Bandung"
              class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('city') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
            @error('city')
            <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
            @enderror
          </div>

          <!-- Area -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Area <span class="font-normal text-gray-400">(opsional)</span></label>
            <input type="text" wire:model="area" placeholder="e.g. Dago, Kemang"
              class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
          </div>
        </div>

        <!-- Payment details -->
        <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
          <p class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">Data pembayaran</p>
          <p class="mb-3 text-xs text-gray-400 dark:text-gray-500">
            Isi metode, nama pemilik, dan nomor rekening, atau kosongkan semuanya bila belum ada. Nomor rekening disimpan terenkripsi.
          </p>

          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
            <!-- Method -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Metode</label>
              <div class="relative">
                <select wire:model="payment_method"
                  class="dark:bg-dark-900 shadow-theme-xs h-11 w-full appearance-none rounded-lg border bg-transparent pl-4 pr-11 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('payment_method') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror">
                  <option value="">Belum diisi</option>
                  @foreach (\App\Models\Partner::PAYMENT_METHODS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                  @endforeach
                </select>
                <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                  <x-svg.chevron-down class="h-4 w-4" />
                </span>
              </div>
              @error('payment_method')
              <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
              @enderror
            </div>

            <!-- Provider -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Bank / E-wallet <span class="font-normal text-gray-400">(opsional)</span></label>
              <input type="text" wire:model="payment_provider" placeholder="e.g. BCA, Mandiri, GoPay"
                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('payment_provider') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
              @error('payment_provider')
              <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
              @enderror
            </div>
            </div>

            <!-- Account name -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Pemilik Rekening</label>
              <input type="text" wire:model="account_name" placeholder="Sesuai nama di rekening atau akun"
                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('account_name') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
              @error('account_name')
              <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
              @enderror
            </div>

            <!-- Account number -->
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nomor Rekening / Akun</label>
              <input type="text" inputmode="numeric" autocomplete="off" wire:model="account_number" placeholder="Angka saja, mis. 1234567890"
                class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('account_number') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
              @if ($accountNumberUnreadable)
                <span class="mt-1 block text-xs text-amber-600 dark:text-amber-400">
                  Nomor rekening yang tersimpan tidak dapat dibaca lagi (kunci enkripsi aplikasi berubah). Masukkan ulang nomornya.
                </span>
              @endif
              @error('account_number')
              <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
              @enderror
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Catatan</label>
          <textarea wire:model="notes" rows="2" placeholder="Catatan tambahan (mis. hanya bisa membeli di akhir pekan)"
            class="dark:bg-dark-900 shadow-theme-xs w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
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
            <x-ui.spinner.spinner-four :icon-only="true" class="w-5 h-5 text-white" wire:loading wire:target="savePartner" />
            <span>Simpan Mitra</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
