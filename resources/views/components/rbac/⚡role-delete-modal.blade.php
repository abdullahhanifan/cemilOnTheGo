<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Spatie\Permission\Models\Role;

new class extends Component {
    public int $roleId = 0;
    public string $roleName = '';
    public int $roleUsersCount = 0;
    public bool $isDeleteOpen = false;

    #[On('open-role-delete')]
    public function openDelete(int $id, string $name, int $usersCount): void
    {
        $this->roleId = $id;
        $this->roleName = $name;
        $this->roleUsersCount = $usersCount;
        $this->isDeleteOpen = true;
    }

    public function deleteRole(): void
    {
        abort_unless(auth()->user()?->can('access-control:roles-delete'), 403);

        try {
            $role = Role::findOrFail($this->roleId);

            if ($role->name === config('alh.super_admin_role')) {
                $msg = 'The super admin role cannot be deleted.';
                session()->flash('error', $msg);
                $this->dispatch('notify', type: 'error', message: $msg);
                $this->isDeleteOpen = false;
                return;
            }

            if ($role->users()->count() > 0) {
                $msg = 'Cannot delete role. It is currently assigned to users.';
                session()->flash('error', $msg);
                $this->dispatch('notify', type: 'error', message: $msg);
                $this->isDeleteOpen = false;
                return;
            }

            $role->delete();
            $this->isDeleteOpen = false;
            $this->resetState();
            $msg = 'Role deleted successfully!';
            session()->flash('success', $msg);
            $this->dispatch('notify', type: 'success', message: $msg);
            $this->dispatch('role-deleted');
        } catch (\Throwable $e) {
            $this->isDeleteOpen = false;
            $msg = 'Failed to delete role: ' . $e->getMessage();
            session()->flash('error', $msg);
            $this->dispatch('notify', type: 'error', message: $msg);
        }
    }

    private function resetState(): void
    {
        $this->roleId = 0;
        $this->roleName = '';
        $this->roleUsersCount = 0;
    }
};
?>

<div>
    <div
        x-data="{ show: @entangle('isDeleteOpen') }"
        x-show="show"
        x-cloak
        class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-5"
    >
        <div @click="show = false" class="fixed inset-0 h-full w-full bg-gray-900/50 backdrop-blur-sm"></div>

        <div class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-950 border border-gray-150 dark:border-gray-800 transition">
            <div class="text-center pb-4">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/10 dark:text-red-400 mb-3">
                    <x-svg.warning class="h-6 w-6" />
                </div>
                <h4 class="text-lg font-bold text-gray-800 dark:text-white">
                    Delete Access Role
                </h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                    Are you sure you want to delete this role? This will permanently delete the role from the system. This cannot be undone.
                </p>
                @if($roleName)
                    <div class="bg-gray-50 dark:bg-white/[0.02] border border-gray-100 dark:border-gray-800 rounded-xl p-3 mt-3">
                        <span class="block font-semibold text-gray-800 text-sm dark:text-white">{{ $roleName }}</span>
                        <span class="block text-gray-400 text-xs mt-0.5">Assigned to: {{ $roleUsersCount }} users</span>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-3 mt-4">
                <button
                    type="button"
                    @click="show = false"
                    class="flex-1 py-2.5 border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5 transition"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    wire:click="deleteRole"
                    wire:loading.attr="disabled"
                    class="flex-1 py-2.5 bg-red-600 text-white rounded-xl text-sm font-semibold hover:bg-red-700 transition shadow-sm flex items-center justify-center gap-2"
                >
                    <x-ui.spinner.spinner-four :icon-only="true" class="w-5 h-5 text-white" wire:loading wire:target="deleteRole" />
                    <span>Delete Role</span>
                </button>
            </div>
        </div>
    </div>
</div>