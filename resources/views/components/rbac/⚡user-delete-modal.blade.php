<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\User;

new class extends Component {
    public int $userId = 0;
    public string $userName = '';
    public string $userUsername = '';
    public bool $isDeleteOpen = false;

    #[On('open-user-delete')]
    public function openDelete(int $id, string $name, string $username): void
    {
        $this->userId = $id;
        $this->userName = $name;
        $this->userUsername = $username;
        $this->isDeleteOpen = true;
    }

    public function deleteUser(): void
    {
        abort_unless(auth()->user()?->can('access-control:users-delete'), 403);

        try {
            $user = User::findOrFail($this->userId);

            if ($user->id === auth()->id()) {
                $this->isDeleteOpen = false;
                $msg = 'You cannot deactivate your own account.';
                session()->flash('error', $msg);
                $this->dispatch('notify', type: 'error', message: $msg);
                return;
            }

            // Deactivate rather than hard-delete: this preserves the account's
            // history (roles, ownership of other records) and can be reversed
            // by re-activating the user from the edit form.
            $user->update(['status' => 'inactive']);
            $this->isDeleteOpen = false;
            $this->resetState();
            $msg = 'User deactivated successfully!';
            session()->flash('success', $msg);
            $this->dispatch('notify', type: 'success', message: $msg);
            $this->dispatch('user-deleted');
        } catch (\Throwable $e) {
            $this->isDeleteOpen = false;
            $msg = 'Failed to deactivate user: ' . $e->getMessage();
            session()->flash('error', $msg);
            $this->dispatch('notify', type: 'error', message: $msg);
        }
    }

    private function resetState(): void
    {
        $this->userId = 0;
        $this->userName = '';
        $this->userUsername = '';
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
          Deactivate User Account
        </h4>
        <p class="mt-2 text-xs text-neutral-label dark:text-gray-400">
          Are you sure you want to deactivate this user? They will no longer be able to sign in, but their account
          and history are kept and can be reactivated later.
        </p>
        @if ($userName)
          <div
            class="mt-3 rounded-xl border border-gray-100 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.02]">
            <span class="block text-sm font-semibold text-gray-800 dark:text-white">{{ $userName }}</span>
            <span class="mt-0.5 block text-xs text-gray-400">Username: {{ $userUsername }}</span>
          </div>
        @endif
      </div>

      <!-- Actions -->
      <div class="mt-4 flex items-center gap-3">
        <button type="button" @click="show = false"
          class="flex-1 rounded-xl border border-gray-200 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5">
          Cancel
        </button>
        <button type="button" wire:click="deleteUser" wire:loading.attr="disabled"
          class="flex-1 rounded-xl bg-red-600 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 flex items-center justify-center gap-2">
          <x-ui.spinner.spinner-four :icon-only="true" class="w-5 h-5 text-white" wire:loading wire:target="deleteUser" />
          <span>Deactivate User</span>
        </button>
      </div>
    </div>
  </div>
</div>