<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Helpers\MenuHelper;

new class extends Component {
    // Form fields
    public int $roleId = 0; // 0 for create, > 0 for edit
    public string $name = '';
    public array $selectedPermissions = [];

    // Modal Control
    public bool $isAddEditOpen = false;

    /**
     * Get all permission names inside a group.
     */
    private function getPermissionsInGroup(string $groupName): array
    {
        $groups = MenuHelper::getPermissionGroups();
        if (!isset($groups[$groupName])) {
            return [];
        }

        $names = [];
        foreach ($groups[$groupName] as $feature => $actions) {
            foreach ($actions as $action => $permName) {
                $names[] = $permName;
            }
        }
        return $names;
    }

    /**
     * Toggle all permissions inside a group.
     */
    public function toggleGroup(string $groupName): void
    {
        $groupPermissions = $this->getPermissionsInGroup($groupName);
        if (empty($groupPermissions)) {
            return;
        }

        if ($this->isGroupAllChecked($groupName)) {
            // Remove all
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $groupPermissions));
        } else {
            // Add all
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $groupPermissions)));
        }
    }

    /**
     * Check if all permissions in a group are selected.
     */
    public function isGroupAllChecked(string $groupName): bool
    {
        $groupPermissions = $this->getPermissionsInGroup($groupName);
        if (empty($groupPermissions)) {
            return false;
        }

        foreach ($groupPermissions as $permission) {
            if (!in_array($permission, $this->selectedPermissions)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Reset form fields.
     */
    public function resetForm(): void
    {
        $this->roleId = 0;
        $this->name = '';
        $this->selectedPermissions = [];
        $this->resetValidation();
    }

    /**
     * Open form modal for Add/Edit
     */
    #[On('open-role-form')]
    public function openForm(?int $id = null): void
    {
        $this->resetForm();
        if ($id) {
            $this->roleId = $id;
            $role = Role::with('permissions')->findOrFail($id);
            $this->name = $role->name;
            $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        }
        $this->isAddEditOpen = true;
    }

    /**
     * Save Role and assign permissions.
     */
    public function saveRole(): void
    {
        $isEdit = $this->roleId > 0;

        abort_unless(
            auth()->user()?->can($isEdit ? 'access-control:roles-edit' : 'access-control:roles-create'),
            403
        );

        $rules = [
            'name' => 'required|string|max:255|unique:roles,name,' . $this->roleId,
            'selectedPermissions' => 'nullable|array',
            'selectedPermissions.*' => 'string|exists:permissions,name',
        ];

        $validated = $this->validate($rules);

        try {
            if ($isEdit) {
                $role = Role::findOrFail($this->roleId);
                $role->update([
                    'name' => $this->name,
                ]);
            } else {
                $role = Role::create([
                    'name' => $this->name,
                ]);
            }

            // Sync Spatie permissions
            $role->syncPermissions($this->selectedPermissions);

            $this->isAddEditOpen = false;
            $this->resetForm();
            $this->dispatch('role-saved');
            $msg = $isEdit ? 'Role updated successfully!' : 'Role created successfully!';
            session()->flash('success', $msg);
            $this->dispatch('notify', type: 'success', message: $msg);
        } catch (\Throwable $e) {
            $this->isAddEditOpen = false;
            $msg = 'Failed to save role: ' . $e->getMessage();
            session()->flash('error', $msg);
            $this->dispatch('notify', type: 'error', message: $msg);
        }
    }
};
?>

<div>
    <!-- Add/Edit Role Modal -->
    <div
        x-data="{ show: @entangle('isAddEditOpen'), selectedPermissions: @entangle('selectedPermissions') }"
        x-show="show"
        x-cloak
        class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-5"
    >
        <!-- Backdrop -->
        <div @click="show = false" class="fixed inset-0 h-full w-full bg-gray-900/50 backdrop-blur-sm"></div>

        <!-- Content -->
        <div class="relative w-full max-w-3xl rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-950 border border-gray-150 dark:border-gray-800 transition">
            <div class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800 mb-5">
                <h4 class="text-xl font-bold text-gray-800 dark:text-white ">
                    {{ $roleId > 0 ? 'Edit Role Configuration' : 'Add New Role' }}
                </h4>
                <button @click="show = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-white transition">
                    <x-svg.x class="h-6 w-6" />
                </button>
            </div>

            <!-- Form -->
            <form wire:submit.prevent="saveRole" class="space-y-4">
                <h5 class="font-bold text-gray-700 dark:text-gray-300 text-sm border-l-2 border-accent-primary pl-2 mb-2 ">
                    Role Information
                </h5>

                <!-- Name -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Role Name</label>
                    <input
                        type="text"
                        wire:model="name"
                        placeholder="e.g. Product & Marketing Officer"
                        class="dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 @error('name') border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700 dark:focus:border-error-800 @else border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800 @enderror"
                        {{ $roleId > 0 && $name === config('alh.super_admin_role') ? 'disabled' : '' }}
                    />
                    @error('name') <span class="text-red-500 text-xs mt-1 block ">{{ $message }}</span> @enderror
                </div>

                <!-- Permissions Checklist Matrix (Collapsible Accordions) -->
                <div class="pt-4 border-t border-gray-100 dark:border-gray-800">
                    <h5 class="font-bold text-gray-700 dark:text-gray-300 text-sm border-l-2 border-accent-primary pl-2 mb-1 ">
                        Role Permission
                    </h5>
                    <p class="text-xs text-gray-400 mb-4 ">
                        Select the permissions this role can access.
                    </p>

                    <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                        @foreach(MenuHelper::getPermissionGroups() as $groupName => $features)
                            <div
                                x-data="{ open: true }"
                                class="border border-gray-100 dark:border-gray-800 rounded-2xl overflow-hidden bg-white dark:bg-white/[0.01] shadow-xs"
                            >
                                <!-- Header -->
                                <div class="flex items-center justify-between px-5 py-4 bg-slate-50 dark:bg-white/[0.02] border-b border-gray-100 dark:border-gray-800 select-none">
                                    <span class="font-bold text-gray-800 dark:text-white text-sm">
                                        {{ $groupName }}
                                    </span>
                                    <div class="flex items-center gap-6">
                                        <!-- Group Checkbox (All) -->
                                        <label class="flex items-center gap-2 cursor-pointer select-none">
                                            <span class="relative">
                                                <input
                                                    type="checkbox"
                                                    class="sr-only"
                                                    wire:click="toggleGroup('{{ $groupName }}')"
                                                    @if($this->isGroupAllChecked($groupName)) checked @endif
                                                    {{ $roleId > 0 && $name === config('alh.super_admin_role') ? 'disabled' : '' }}
                                                />
                                                <span
                                                    class="flex h-5 w-5 items-center justify-center rounded-md border-[1.25px] transition-all duration-200 hover:border-brand-500 dark:hover:border-brand-500"
                                                    :class="{{ $this->isGroupAllChecked($groupName) ? 'true' : 'false' }}
                                                        ? 'border-brand-500 bg-brand-500'
                                                        : 'bg-transparent border-gray-300 dark:border-gray-700'"
                                                >
                                                    <span :class="{{ $this->isGroupAllChecked($groupName) ? 'true' : 'false' }} ? 'opacity-100' : 'opacity-0'" class="transition-opacity duration-200">
                                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="1.94437"
                                                                stroke-linecap="round" stroke-linejoin="round" />
                                                        </svg>
                                                    </span>
                                                </span>
                                            </span>
                                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 ">All</span>
                                        </label>
                                        <!-- Collapse Toggle -->
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors"
                                        >
                                            <x-svg.chevron-down
                                                class="w-4 h-4 transition-transform duration-200"
                                                ::class="{ 'rotate-180': !open }"
                                                stroke-width="2.5"
                                            />
                                        </button>
                                    </div>
                                </div>

                                <!-- Content Table -->
                                <div x-show="open" class="p-4 bg-white dark:bg-transparent overflow-x-auto">
                                    <table class="w-full text-left text-sm ">
                                        <thead>
                                            <tr class="text-[11px] font-bold text-gray-400 dark:text-gray-500 uppercase border-b border-gray-100 dark:border-gray-800">
                                                <th class="pb-2 font-semibold text-gray-500 dark:text-gray-400">Feature</th>
                                                <th class="pb-2 text-center font-semibold text-gray-500 dark:text-gray-400 w-16">View</th>
                                                <th class="pb-2 text-center font-semibold text-gray-500 dark:text-gray-400 w-16">Create</th>
                                                <th class="pb-2 text-center font-semibold text-gray-500 dark:text-gray-400 w-16">Edit</th>
                                                <th class="pb-2 text-center font-semibold text-gray-500 dark:text-gray-400 w-16">Delete</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                            @foreach($features as $featureName => $actions)
                                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.01] transition-colors">
                                                    <td class="py-3 font-medium text-gray-700 dark:text-gray-300 text-sm">
                                                        {{ $featureName }}
                                                    </td>
                                                    @foreach(['view', 'create', 'edit', 'delete'] as $action)
                                                        <td class="py-3 text-center">
                                                            @if(isset($actions[$action]))
                                                                <label class="inline-flex cursor-pointer items-center select-none justify-center">
                                                                    <span class="relative">
                                                                        <input
                                                                            type="checkbox"
                                                                            wire:model="selectedPermissions"
                                                                            value="{{ $actions[$action] }}"
                                                                            class="sr-only"
                                                                            {{ $roleId > 0 && $name === config('alh.super_admin_role') ? 'disabled' : '' }}
                                                                        />
                                                                        <span
                                                                            class="flex h-5 w-5 items-center justify-center rounded-md border-[1.25px] transition-all duration-200 hover:border-brand-500 dark:hover:border-brand-500"
                                                                            :class="selectedPermissions.includes('{{ $actions[$action] }}')
                                                                                ? 'border-brand-500 bg-brand-500'
                                                                                : 'bg-transparent border-gray-300 dark:border-gray-750'"
                                                                        >
                                                                            <span :class="selectedPermissions.includes('{{ $actions[$action] }}') ? 'opacity-100' : 'opacity-0'" class="transition-opacity duration-200">
                                                                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                                    <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="1.94437"
                                                                                        stroke-linecap="round" stroke-linejoin="round" />
                                                                                </svg>
                                                                            </span>
                                                                        </span>
                                                                    </span>
                                                                </label>
                                                            @else
                                                                <span class="text-gray-300 dark:text-gray-700 text-xs">-</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-5 border-t border-gray-100 dark:border-gray-800 mt-6">
                    <button
                        type="button"
                        @click="show = false"
                        class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5 transition "
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="px-5 py-2.5 bg-accent-primary dark:bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-accent-primary-hover dark:hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-2"
                    >
                        <x-ui.spinner.spinner-four :icon-only="true" class="w-5 h-5 text-white" wire:loading wire:target="saveRole" />
                        <span>Save Role</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
