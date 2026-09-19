<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\User;

new class extends Component {
    public ?User $selectedUser = null;
    public bool $isDetailOpen = false;

    #[On('open-user-detail')]
    public function openDetail(int $userId): void
    {
        $this->selectedUser = User::with('roles')->findOrFail($userId);
        $this->isDetailOpen = true;
    }
};
?>

<div>
  <!-- User Detail Modal -->
  <div x-data="{ show: @entangle('isDetailOpen') }" x-show="show" x-cloak
    class="z-99999 fixed inset-0 flex items-center justify-center overflow-y-auto p-5">
    <div @click="show = false" class="fixed inset-0 h-full w-full bg-gray-900/50 backdrop-blur-sm"></div>

    <div
      class="border-gray-150 relative w-full max-w-md rounded-[20px] border bg-white p-6 font-sans shadow-xl transition dark:border-gray-800 dark:bg-gray-950">
      <div class="mb-5 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800">
        <h4 class="text-lg font-bold text-gray-800 dark:text-white">
          User Details
        </h4>
        <button @click="show = false" class="text-gray-400 transition hover:text-gray-600 dark:hover:text-white">
          <x-svg.x class="h-6 w-6" />
        </button>
      </div>

      @if ($selectedUser)
        <div class="flex flex-col items-center border-b border-gray-100 pb-6 text-center dark:border-gray-800">
          <div
            class="mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-accent-primary/10 text-xl font-bold text-accent-primary dark:bg-blue-600/10 dark:text-blue-400">
            {{ strtoupper(substr($selectedUser->name, 0, 2)) }}
          </div>
          <h5 class="text-lg font-bold text-gray-800 dark:text-white">
            {{ $selectedUser->name }}
          </h5>
          <span
            class="mt-1 inline-flex rounded-lg bg-accent-primary/5 px-2.5 py-1 text-xs font-semibold text-accent-primary dark:bg-blue-600/10 dark:text-blue-400">
            {{ $selectedUser->roles->first()?->name ?? 'No Role' }}
          </span>
        </div>

        <div class="space-y-3.5 py-4 text-sm">
          <div class="flex justify-between">
            <span class="text-neutral-label">Username:</span>
            <span class="font-semibold text-gray-800 dark:text-white">{{ $selectedUser->username }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-label">Email:</span>
            <span class="font-semibold text-gray-800 dark:text-white">{{ $selectedUser->email }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-label">Status:</span>
            @if ($selectedUser->status === 'active')
              <span class="font-semibold text-green-600 dark:text-green-400">Active</span>
            @else
              <span class="font-semibold text-gray-500">Inactive</span>
            @endif
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-label">Registered:</span>
            <span class="font-semibold text-gray-800 dark:text-white">
              {{ $selectedUser->created_at ? $selectedUser->created_at->format('d M Y H:i') : '-' }}
            </span>
          </div>
          <div class="flex justify-between">
            <span class="text-neutral-label">Last Updated:</span>
            <span class="font-semibold text-gray-800 dark:text-white">
              {{ $selectedUser->updated_at ? $selectedUser->updated_at->format('d M Y H:i') : '-' }}
            </span>
          </div>
        </div>
      @endif

      <div class="mt-4 flex items-center justify-end border-t border-gray-100 pt-4 dark:border-gray-800">
        <button type="button" @click="show = false"
          class="w-full rounded-xl bg-gray-100 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10">
          Close Details
        </button>
      </div>
    </div>
  </div>
</div>
