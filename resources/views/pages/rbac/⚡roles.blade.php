<?php

use App\Helpers\MenuHelper;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

new #[Layout('layouts.app')] #[Title('Role & Permission Management')] class extends Component {
    public function mount()
    {
        abort_unless(auth()->user()?->can('access-control:roles-view'), 403);
    }

    // Search & Sorting
    public string $search = '';
    public string $sortColumn = 'id';
    public string $sortDirection = 'asc';

    protected $queryString = [
        'search' => ['except' => ''],
        'sortColumn' => ['except' => 'id'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function sortBy(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * Get roles with count of users and permissions loaded.
     * Cached via #[Computed] to avoid re-executing on modal open.
     */
    #[Computed]
    public function roles()
    {
        $query = Role::with('permissions')
            ->withCount('users')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            });

        if ($this->sortColumn === 'users_count') {
            $query->orderBy('users_count', $this->sortDirection);
        } else {
            $query->orderBy($this->sortColumn, $this->sortDirection);
        }

        return $query->get();
    }

    /**
     * Event listener to refresh the roles list when a role is saved in the child component.
     */
    #[On('role-saved')]
    public function refreshRoles()
    {
        unset($this->roles);
    }

    #[On('role-deleted')]
    public function refreshRolesAfterDelete()
    {
        unset($this->roles);
    }

    /**
     * Export CSV.
     */
    public function exportCsv()
    {
        $roles = Role::with('permissions')->withCount('users')->get();
        $csvFileName = 'roles_export_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$csvFileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['ID', 'Role Name', 'Access Scope (Permissions)', 'Users Assigned', 'Created At'];

        $callback = function() use($roles, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($roles as $role) {
                fputcsv($file, [
                    $role->id,
                    $role->name,
                    implode(', ', $role->permissions->pluck('name')->toArray()),
                    $role->users_count,
                    $role->created_at ? $role->created_at->format('Y-m-d H:i:s') : '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
};
?>
<div class="">

    <!-- Card Container -->
    <div class="min-h-screen rounded-2xl border border-gray-200 bg-white px-5 py-7 dark:border-gray-800 dark:bg-white/[0.03] xl:px-10 xl:py-12">

        <!-- Header Section -->
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                    Configure Roles and Permissions
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Create roles and define the permissions available to each role
                </p>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <button
                    onclick="window.print()"
                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/5 transition"
                >
                    <x-svg.print class="h-4 w-4" />
                    <span>Print</span>
                </button>

                <button
                    wire:click="exportCsv"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/5 transition disabled:opacity-75"
                >
                    <x-svg.export class="h-4 w-4" wire:loading.remove wire:target="exportCsv" />
                    <x-ui.spinner.spinner-four :icon-only="true" class="w-4 h-4 text-gray-700 dark:text-gray-300" wire:loading wire:target="exportCsv" />
                    <span>Export CSV</span>
                </button>

                <button
                    wire:click="$dispatch('open-role-form')"
                    class="inline-flex items-center gap-2 rounded-xl bg-accent-primary dark:bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-accent-primary-hover dark:hover:bg-blue-700 transition shadow-sm"
                >
                    <x-svg.plus class="h-4 w-4" />
                    <span>Add Role</span>
                </button>
            </div>
        </div>


        <!-- Filter Bar -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
            <!-- Search -->
            <div class="relative flex-1 max-w-md">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                    <x-svg.search class="h-5 w-5" />
                </span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search role name..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-800 focus:border-accent-primary focus:ring-1 focus:ring-accent-primary dark:border-gray-800 dark:bg-white/[0.03] dark:text-white dark:focus:border-blue-500 outline-none transition text-sm"
                />
            </div>
        </div>

        <!-- Table View -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
            <div class="max-w-full overflow-x-auto custom-scrollbar">
                <table class="w-full min-w-[900px]">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50 dark:border-gray-800 dark:bg-white/[0.01]">
                            <x-tables.th-sortable column="id" :sortColumn="$sortColumn" :sortDirection="$sortDirection">
                                ID
                            </x-tables.th-sortable>
                            <x-tables.th-sortable column="name" :sortColumn="$sortColumn" :sortDirection="$sortDirection">
                                Role Name
                            </x-tables.th-sortable>
                            <x-tables.th-sortable :sortable="false">
                                Access Scope
                            </x-tables.th-sortable>
                            <x-tables.th-sortable column="users_count" :sortColumn="$sortColumn" :sortDirection="$sortDirection" align="center">
                                User Assigned
                            </x-tables.th-sortable>
                            <x-tables.th-sortable column="updated_at" :sortColumn="$sortColumn" :sortDirection="$sortDirection" align="center">
                                Last Update
                            </x-tables.th-sortable>
                            <x-tables.th-sortable :sortable="false" align="center">
                                Action
                            </x-tables.th-sortable>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->roles as $role)
                            <tr wire:key="role-{{ $role->id }}" class="hover:bg-gray-50/30 dark:hover:bg-white/[0.01] transition-colors">
                                <!-- ID -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-semibold text-neutral-heading dark:text-white/90">
                                        #{{ $role->id }}
                                    </span>
                                </td>
                                <!-- Role Name -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-accent-primary/10 text-accent-primary dark:bg-blue-600/10 dark:text-blue-400 font-semibold text-sm">
                                            {{ strtoupper(substr($role->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <span class="block font-semibold text-gray-800 text-sm dark:text-white/90">
                                                {{ $role->name }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <!-- Access Scope (Permissions) - Grouped Tree View -->
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300 min-w-[340px] max-w-lg">
                                    @php
                                        $grouped = MenuHelper::groupPermissionsByMenu($role->permissions->pluck('name'));
                                        $actionColors = [
                                            'view'   => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-500/20',
                                            'create' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20',
                                            'edit'   => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20',
                                            'delete' => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/20',
                                        ];
                                        $actionOrder = ['view', 'create', 'edit', 'delete'];
                                    @endphp
                                    @if(empty($grouped))
                                        <span class="text-gray-400 text-sm italic">No permissions mapped</span>
                                    @else
                                        <div x-data="{ open: false }" class="space-y-1">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                @foreach(array_keys($grouped) as $module)
                                                    @php $total = array_sum(array_map('count', $grouped[$module])); @endphp
                                                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/5 px-3 py-1 text-sm font-semibold text-gray-600 dark:text-gray-300">
                                                        <span class="w-2 h-2 rounded-full bg-accent-primary dark:bg-blue-400 inline-block flex-shrink-0"></span>
                                                        {{ $module }}
                                                        <span class="text-gray-400 dark:text-gray-500 font-normal">({{ $total }})</span>
                                                    </span>
                                                @endforeach
                                                <button
                                                    type="button"
                                                    @click="open = !open"
                                                    class="inline-flex items-center gap-1 text-sm font-semibold text-accent-primary dark:text-blue-400 hover:underline transition-colors ml-1"
                                                >
                                                    <span x-text="open ? 'Hide' : 'Details'"></span>
                                                    <x-svg.chevron-down class="w-4 h-4 transition-transform duration-200" ::class="{ 'rotate-180': open }" stroke-width="2.5" />
                                                </button>
                                            </div>

                                            <div x-show="open" x-transition class="pt-1.5 space-y-2">
                                                @foreach($grouped as $module => $features)
                                                    <div class="rounded-xl border border-gray-100 dark:border-gray-800 overflow-hidden">
                                                        <div class="flex items-center gap-2 px-4 py-2 bg-accent-primary/5 dark:bg-blue-500/10 border-b border-gray-100 dark:border-gray-800">
                                                            <span class="text-sm font-extrabold text-accent-primary dark:text-blue-400 uppercase tracking-widest">
                                                                {{ $module }}
                                                            </span>
                                                            <span class="ml-auto text-sm font-medium text-gray-400 dark:text-gray-500">
                                                                {{ count($features) }} {{ Str::plural('feature', count($features)) }}
                                                            </span>
                                                        </div>

                                                        <div class="divide-y divide-gray-50 dark:divide-gray-800/50 bg-white dark:bg-transparent">
                                                            @foreach($features as $feature => $actions)
                                                                <div class="flex items-center justify-between gap-3 px-4 py-2 hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                                        {{ $feature }}
                                                                    </span>
                                                                    <div class="grid grid-cols-2 gap-1 w-36 shrink-0">
                                                                        @foreach($actionOrder as $act)
                                                                            @if(in_array($act, $actions))
                                                                                <span class="inline-flex items-center justify-center rounded-md border px-1.5 py-0.5 text-sm font-semibold text-center leading-tight {{ $actionColors[$act] }}">
                                                                                    {{ $act }}
                                                                                </span>
                                                                            @else
                                                                                <span class="inline-flex items-center justify-center rounded-md border border-transparent px-1.5 py-0.5 text-sm text-transparent select-none">
                                                                                    {{ $act }}
                                                                                </span>
                                                                            @endif
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </td>
                                <!-- Users count -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-white font-semibold text-center">
                                    {{ $role->users_count }} users
                                </td>
                                <!-- Last Update -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                    {{ $role->updated_at ? $role->updated_at->format('d/m/y, H:i') : '-' }}
                                </td>
                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-3">
                                        <!-- Edit Button -->
                                        <button
                                            wire:click="$dispatch('open-role-form', { id: {{ $role->id }} })"
                                            class="text-gray-500 hover:text-amber-600 dark:text-gray-400 dark:hover:text-amber-400 transition-colors p-1"
                                            title="Edit"
                                        >
                                            <x-svg.pencil class="w-5 h-5" />
                                        </button>

                                        <!-- Delete Button -->
                                        @if($role->name !== config('alh.super_admin_role'))
                                            <button
                                                wire:click="$dispatch('open-role-delete', { id: {{ $role->id }}, name: '{{ addslashes($role->name) }}', usersCount: {{ $role->users_count }} })"
                                                class="text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 transition-colors p-1"
                                                title="Delete"
                                            >
                                                <x-svg.trash class="w-5 h-5" />
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-400 italic dark:text-gray-500">
                                    No roles found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modular Modals -->
    <livewire:rbac.role-form-modal />
    <livewire:rbac.role-delete-modal />
</div>
