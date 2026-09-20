<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Partner;

new class extends Component {
    public int $partnerId = 0;
    public string $partnerName = '';
    public bool $isDeactivateOpen = false;

    #[On('open-partner-deactivate')]
    public function openDeactivate(int $id, string $name): void
    {
        $this->partnerId = $id;
        $this->partnerName = $name;
        $this->isDeactivateOpen = true;
    }

    /**
     * Authorized here, not just on the page that dispatched the open event —
     * see the note in partner-form-modal.blade.php.
     *
     * Partners are deactivated through `status`, never hard-deleted, because
     * purchases and payments will reference them. They can be reactivated from
     * the edit form.
     */
    public function deactivatePartner(): void
    {
        abort_unless(auth()->user()?->can('partner:partner-delete'), 403);

        try {
            $partner = Partner::findOrFail($this->partnerId);
            $partner->update(['status' => 'inactive']);
            $this->isDeactivateOpen = false;
            $this->resetState();
            $msg = 'Mitra berhasil dinonaktifkan!';
            session()->flash('success', $msg);
            $this->dispatch('notify', type: 'success', message: $msg);
            $this->dispatch('partner-deactivated');
        } catch (\Throwable $e) {
            report($e);
            $this->isDeactivateOpen = false;
            $msg = 'Gagal menonaktifkan mitra. Silakan coba lagi.';
            session()->flash('error', $msg);
            $this->dispatch('notify', type: 'error', message: $msg);
        }
    }

    private function resetState(): void
    {
        $this->partnerId = 0;
        $this->partnerName = '';
    }
};
?>

<div>
  <div x-data="{ show: @entangle('isDeactivateOpen') }" x-show="show" x-cloak
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
          Nonaktifkan Mitra
        </h4>
        <p class="mt-2 text-xs text-neutral-label dark:text-gray-400">
          Mitra ini akan ditandai nonaktif. Datanya tetap tersimpan dan bisa diaktifkan kembali lewat form edit.
        </p>
        @if ($partnerName)
          <div class="mt-3 rounded-xl border border-gray-100 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.02]">
            <span class="block text-sm font-semibold text-gray-800 dark:text-white">{{ $partnerName }}</span>
          </div>
        @endif
      </div>

      <div class="mt-4 flex items-center gap-3">
        <button type="button" @click="show = false"
          class="flex-1 rounded-xl border border-gray-200 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5">
          Batal
        </button>
        <button type="button" wire:click="deactivatePartner" wire:loading.attr="disabled"
          class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-red-600 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
          <x-ui.spinner.spinner-four :icon-only="true" class="w-5 h-5 text-white" wire:loading wire:target="deactivatePartner" />
          <span>Nonaktifkan Mitra</span>
        </button>
      </div>
    </div>
  </div>
</div>
