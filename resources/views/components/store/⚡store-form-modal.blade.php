<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Store;

new class extends Component {
  public int $storeId = 0; // 0 for create, > 0 for edit

  public string $name = '';
  public string $city = '';
  public string $area = '';
  public string $address = '';
  public string $contact_name = '';
  public string $contact_phone = '';
  public string $notes = '';
  public string $status = 'active';

  /**
   * Opening hours: one row per day range, e.g. Senin–Jumat 10:00–22:00.
   * Days are ISO-8601 numbers (1 = Monday ... 7 = Sunday).
   *
   * @var list<array{day_from: int|string, day_to: int|string, opens_at: string, closes_at: string}>
   */
  public array $schedules = [];

  public bool $isAddEditOpen = false;

  /**
   * Opening the form is authorized too: the edit form loads a store's data into
   * component state, and event listeners are as reachable over the wire as any
   * other public method.
   */
  #[On('open-store-form')]
  public function openForm(?int $id = null): void
  {
    abort_unless(
      auth()->user()?->can($id ? 'store:store-edit' : 'store:store-create'),
      403
    );

    $this->resetForm();
    if ($id) {
      $store = Store::findOrFail($id);
      $this->storeId = $store->id;
      $this->name = $store->name;
      $this->city = $store->city;
      $this->area = $store->area ?? '';
      $this->address = $store->address ?? '';
      $this->contact_name = $store->contact_name ?? '';
      $this->contact_phone = $store->contact_phone ?? '';
      $this->notes = $store->notes ?? '';
      $this->status = $store->status;
      $this->schedules = $store->opening_hours ?? [];
    }
    $this->isAddEditOpen = true;
  }

  public function resetForm(): void
  {
    $this->storeId = 0;
    $this->name = '';
    $this->city = '';
    $this->area = '';
    $this->address = '';
    $this->contact_name = '';
    $this->contact_phone = '';
    $this->notes = '';
    $this->status = 'active';
    $this->schedules = [];
    $this->resetValidation();
  }

  public function addSchedule(): void
  {
    if (count($this->schedules) < 7) {
      $this->schedules[] = ['day_from' => 1, 'day_to' => 7, 'opens_at' => '', 'closes_at' => ''];
    }
  }

  public function removeSchedule(int $index): void
  {
    unset($this->schedules[$index]);
    $this->schedules = array_values($this->schedules);
    $this->resetValidation();
  }

  /**
   * Authorization happens here, on the action itself — not only on the page
   * that dispatches the "open-store-form" event. A Livewire component's
   * public methods are reachable directly over the wire, so route- or
   * mount()-level checks on the parent page do not protect this action.
   */
  public function saveStore(): void
  {
    $isEdit = $this->storeId > 0;

    abort_unless(
      auth()->user()?->can($isEdit ? 'store:store-edit' : 'store:store-create'),
      403
    );

    $validated = $this->validate([
      'name' => 'required|string|max:255',
      'city' => 'required|string|max:100',
      'area' => 'nullable|string|max:255',
      'address' => 'nullable|string|max:1000',
      'contact_name' => 'nullable|string|max:255',
      'contact_phone' => 'nullable|string|max:50',
      'notes' => 'nullable|string|max:2000',
      'status' => 'required|string|in:active,inactive',
      'schedules' => 'array|max:7',
      'schedules.*.day_from' => 'required|integer|between:1,7',
      'schedules.*.day_to' => 'required|integer|between:1,7|gte:schedules.*.day_from',
      'schedules.*.opens_at' => 'required|date_format:H:i',
      'schedules.*.closes_at' => 'required|date_format:H:i',
    ], [
      'required' => ':attribute wajib diisi.',
      'max' => ':attribute maksimal :max karakter.',
      'in' => ':attribute tidak valid.',
      'integer' => ':attribute tidak valid.',
      'between' => ':attribute tidak valid.',
      'gte' => ':attribute tidak boleh sebelum hari mulai.',
      'date_format' => ':attribute harus berformat JJ:MM.',
      'schedules.max' => 'Jadwal buka maksimal 7 baris.',
    ], [
      'name' => 'Nama toko',
      'city' => 'Kota',
      'area' => 'Area',
      'address' => 'Alamat',
      'contact_name' => 'Nama kontak',
      'contact_phone' => 'Telepon',
      'notes' => 'Catatan',
      'status' => 'Status',
      'schedules.*.day_from' => 'Hari mulai',
      'schedules.*.day_to' => 'Hari selesai',
      'schedules.*.opens_at' => 'Jam buka',
      'schedules.*.closes_at' => 'Jam tutup',
    ]);

    $schedules = array_map(fn (array $row) => [
      'day_from' => (int) $row['day_from'],
      'day_to' => (int) $row['day_to'],
      'opens_at' => $row['opens_at'],
      'closes_at' => $row['closes_at'],
    ], $validated['schedules'] ?? []);

    $data = collect($validated)
      ->except('schedules')
      ->map(fn ($value) => $value === '' ? null : $value)
      ->all();
    $data['opening_hours'] = $schedules ?: null;

    $store = $isEdit ? Store::findOrFail($this->storeId) : null;

    // Deactivating needs the delete permission whether it is done from the
    // deactivate modal or by flipping the status here. Reactivating only
    // needs edit. This must stay outside the try block below: it would
    // swallow the 403 and turn it into a notification.
    abort_if(
      $store?->status === 'active'
        && $data['status'] === 'inactive'
        && ! auth()->user()?->can('store:store-delete'),
      403
    );

    try {
      if ($store) {
        $store->update($data);
      } else {
        Store::create($data);
      }

      $this->isAddEditOpen = false;
      $this->resetForm();
      $this->dispatch('store-saved');
      $msg = $isEdit ? 'Toko berhasil diperbarui!' : 'Toko berhasil ditambahkan!';
      session()->flash('success', $msg);
      $this->dispatch('notify', type: 'success', message: $msg);
    } catch (\Throwable $e) {
      report($e);
      $this->isAddEditOpen = false;
      $msg = 'Gagal menyimpan toko. Silakan coba lagi.';
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
          {{ $storeId > 0 ? 'Edit Toko' : 'Tambah Toko' }}
        </h4>
        <button @click="show = false" class="text-gray-400 transition hover:text-gray-600 dark:hover:text-white">
          <x-svg.x class="h-6 w-6" />
        </button>
      </div>

      <form wire:submit.prevent="saveStore" class="space-y-4">
        <!-- Name -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Toko</label>
          <input type="text" wire:model="name" placeholder="e.g. Kue Mekar"
            class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 @error('name') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 @enderror" />
          @error('name')
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
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Area / Lokasi</label>
            <input type="text" wire:model="area" placeholder="e.g. Paris Van Java, Pasar Baru"
              class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
          </div>
        </div>

        <!-- Address -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Alamat</label>
          <textarea wire:model="address" rows="2" placeholder="Alamat lengkap toko"
            class="dark:bg-dark-900 shadow-theme-xs w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <!-- Contact Name -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama Kontak</label>
            <input type="text" wire:model="contact_name" placeholder="e.g. Budi Santoso"
              class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
          </div>

          <!-- Contact Phone -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Telepon</label>
            <input type="text" wire:model="contact_phone" placeholder="e.g. 0812xxxxxxx"
              class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
          </div>
        </div>

        <!-- Opening hours -->
        <div>
          <div class="mb-1.5 flex items-center justify-between">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">Jam Buka</label>
            @if (count($schedules) < 7)
              <button type="button" wire:click="addSchedule"
                class="text-sm font-semibold text-accent-primary transition hover:text-accent-primary-hover">
                + Tambah jadwal
              </button>
            @endif
          </div>

          @forelse ($schedules as $i => $schedule)
            <div wire:key="schedule-{{ $i }}"
              class="mb-2 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
              <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                <select wire:model="schedules.{{ $i }}.day_from"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                  @foreach (\App\Models\Store::DAYS as $number => $label)
                    <option value="{{ $number }}">{{ $label }}</option>
                  @endforeach
                </select>
                <span class="text-sm text-gray-500 dark:text-gray-400">s/d</span>
                <select wire:model="schedules.{{ $i }}.day_to"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                  @foreach (\App\Models\Store::DAYS as $number => $label)
                    <option value="{{ $number }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>

              <div class="mt-2 grid grid-cols-[1fr_auto_1fr_auto] items-center gap-2">
                <input type="time" wire:model="schedules.{{ $i }}.opens_at"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                <span class="text-sm text-gray-500 dark:text-gray-400">–</span>
                <input type="time" wire:model="schedules.{{ $i }}.closes_at"
                  class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:ring-3 focus:outline-hidden focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                <button type="button" wire:click="removeSchedule({{ $i }})" title="Hapus jadwal"
                  class="p-1 text-gray-400 transition hover:text-red-600 dark:hover:text-red-400">
                  <x-svg.x class="h-5 w-5" />
                </button>
              </div>

              @foreach (['day_from', 'day_to', 'opens_at', 'closes_at'] as $field)
                @error("schedules.{$i}.{$field}")
                <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
                @enderror
              @endforeach
            </div>
          @empty
            <p class="text-xs text-gray-400 dark:text-gray-500">
              Belum ada jadwal. Klik "Tambah jadwal" untuk mengisi hari dan jam buka.
            </p>
          @endforelse

          @error('schedules')
          <span class="mt-1 block text-xs text-red-500">{{ $message }}</span>
          @enderror
        </div>

        <!-- Notes -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Catatan</label>
          <textarea wire:model="notes" rows="2" placeholder="Catatan tambahan (mis. antrean panjang di akhir pekan)"
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
            <x-ui.spinner.spinner-four :icon-only="true" class="w-5 h-5 text-white" wire:loading wire:target="saveStore" />
            <span>Simpan Toko</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
