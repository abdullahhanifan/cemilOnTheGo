<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Vendor;

new class extends Component {
    public int $vendorId = 0;
    public string $vendorName = '';
    public bool $isDeleteOpen = false;

    #[On('open-vendor-delete')]
    public function openDelete(int $id, string $name): void
    {
        $this->vendorId = $id;
        $this->vendorName = $name;
        $this->isDeleteOpen = true;
    }

    /**
     * Authorized here, not just on the page that dispatched the open event —
     * see the note in vendor-form-modal.blade.php.
     */
    public function deleteVendor(): void
    {
        abort_unless(auth()->user()?->can('vendor:vendor-delete'), 403);

        try {
            $vendor = Vendor::findOrFail($this->vendorId);
            $vendor->delete();
            $this->isDeleteOpen = false;
            $this->resetState();
            $msg = 'Vendor berhasil dihapus!';
            session()->flash('success', $msg);
            $this->dispatch('notify', type: 'success', message: $msg);
            $this->dispatch('vendor-deleted');
        } catch (\Throwable $e) {
            $this->isDeleteOpen = false;
            $msg = 'Gagal menghapus vendor: ' . $e->getMessage();
            session()->flash('error', $msg);
            $this->dispatch('notify', type: 'error', message: $msg);
        }
    }

    private function resetState(): void
    {
        $this->vendorId = 0;
        $this->vendorName = '';
    }
};
?>

<div>
  <div x-data="{ show: @entangle('isDeleteOpen') }" x-show="show" x-cloak
    class="z-99999 fixed inset-0 flex items-center justify-center overflow-y-auto p-5">
    <div @click="show = false" class="fixed inset-0 h-full w-full bg-gray-900/50 backdrop-blur-sm"></div>

    <div
      class="border-gray-150 relative w-full max-w-sm rounded-[20px] border bg-white p-6 font-sans shadow-xl transition dark:border-gray-800 dark:bg-gray-950">
      <div class="pb-4 text-center">
        <div
          class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/10 dark:text-red-400">
          <x-svg.warning class="h-6 w-6" />
        </div>
        <h4 class="text-lg font-bold text-gray-800 dark:text-white">
          Hapus Data Vendor
        </h4>
        <p class="mt-2 text-xs text-neutral-label dark:text-gray-400">
          Data vendor ini akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.
        </p>
        @if ($vendorName)
          <div class="mt-3 rounded-xl border border-gray-100 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.02]">
            <span class="block text-sm font-semibold text-gray-800 dark:text-white">{{ $vendorName }}</span>
          </div>
        @endif
      </div>

      <div class="mt-4 flex items-center gap-3">
        <button type="button" @click="show = false"
          class="flex-1 rounded-xl border border-gray-200 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5">
          Batal
        </button>
        <button type="button" wire:click="deleteVendor" wire:loading.attr="disabled"
          class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-red-600 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
          <x-ui.spinner.spinner-four :icon-only="true" class="w-5 h-5 text-white" wire:loading wire:target="deleteVendor" />
          <span>Hapus Vendor</span>
        </button>
      </div>
    </div>
  </div>
</div>
