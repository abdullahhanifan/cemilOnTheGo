<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] #[Title('User Access Management')] class extends Component {
    use WithPagination;

    public function mount()
    {
        abort_unless(auth()->user()?->can('access-control:users-view'), 403);
    }

    // Search and Filters
    public string $search = '';
    public string $filterRole = '';
    public string $filterStatus = '';

    // Temporary variables for Apply/Reset pattern
    public string $tempFilterRole = '';
    public string $tempFilterStatus = '';

    public int $perPage = 10;
    public string $sortColumn = 'id';
    public string $sortDirection = 'asc';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterRole' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'sortColumn' => ['except' => 'id'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    #[Computed]
    public function allRoles()
    {
        return Role::orderBy('name')->get();
    }

    /**
     * Get users. Cached via #[Computed] to avoid re-executing on modal open.
     */
    #[Computed]
    public function users()
    {
        $query = User::with('roles')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('users.name', 'like', '%' . $this->search . '%')
                        ->orWhere('users.username', 'like', '%' . $this->search . '%')
                        ->orWhere('users.email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterRole, function ($query) {
                $query->role($this->filterRole);
            })
            ->when($this->filterStatus, function ($query) {
                $query->where('users.status', $this->filterStatus);
            });

        if ($this->sortColumn === 'role') {
            $query->select('users.*')
                ->leftJoin('model_has_roles', function ($join) {
                    $join->on('users.id', '=', 'model_has_roles.model_id')
                        ->where('model_has_roles.model_type', '=', User::class);
                })
                ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->orderBy('roles.name', $this->sortDirection);
        } else {
            $query->orderBy('users.' . $this->sortColumn, $this->sortDirection);
        }

        return $query->paginate($this->perPage);
    }

    /**
     * Apply Filters.
     */
    public function applyFilters()
    {
        $this->filterRole = $this->tempFilterRole;
        $this->filterStatus = $this->tempFilterStatus;
        $this->resetPage();
    }

    /**
     * Reset Filters.
     */
    public function resetFilters()
    {
        $this->tempFilterRole = '';
        $this->tempFilterStatus = '';
        $this->filterRole = '';
        $this->filterStatus = '';
        $this->resetPage();
    }

    /**
     * Event listener to refresh the user list when a user is saved in the child component.
     */
    #[On('user-saved')]
    public function refreshUsers()
    {
        unset($this->users);
    }

    #[On('user-deleted')]
    public function refreshUsersAfterDelete()
    {
        unset($this->users);
    }

    /**
     * Export CSV.
     */
    public function exportCsv()
    {
        $users = User::with('roles')->get();
        $csvFileName = 'users_export_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$csvFileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = ['ID', 'Name', 'Username', 'Email', 'Role', 'Status'];

        $callback = function () use ($users, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($users as $user) {
                fputcsv($file, [$user->id, $user->name, $user->username, $user->email, $user->roles->first()?->name ?? 'None', $user->status]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
};
?>

<div class="">

  <!-- Main Container Grid -->
  <div class="space-y-6">

    <!-- Table and Controls Wrapper Card -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 xl:p-8 dark:border-gray-800 dark:bg-white/[0.03]">

      <!-- Header Section -->
      <div class="mb-6 flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
        <div>
          <h3 class="text-2xl font-bold tracking-tight text-gray-800 dark:text-white">
            Manage User Access
          </h3>
          <p class="mt-1 text-sm text-neutral-label dark:text-gray-400">
            Add users and assign roles to manage access permissions
          </p>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3">
          <button onclick="window.print()"
            class="inline-flex items-center gap-2 rounded-xl border border-neutral-border bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/5">
            <x-svg.print class="h-4 w-4" />
            <span class="">Print</span>
          </button>

          <button wire:click="exportCsv" wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 rounded-xl border border-neutral-border bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/5 disabled:opacity-75">
            <x-svg.export class="h-4 w-4" wire:loading.remove wire:target="exportCsv" />
            <x-ui.spinner.spinner-four :icon-only="true" class="w-4 h-4 text-gray-700 dark:text-gray-300" wire:loading wire:target="exportCsv" />
            <span class="">Export CSV</span>
          </button>

          <button wire:click="$dispatch('open-user-form')"
            class="inline-flex items-center gap-2 rounded-xl bg-accent-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-accent-primary-hover dark:bg-blue-600 dark:hover:bg-blue-700">
            <x-svg.plus class="h-4 w-4" />
            <span class="">Add User</span>
          </button>
        </div>
      </div>


      <!-- Search & Filters Container -->
      <div class="mb-6 space-y-4">
        <!-- Search bar -->
        <div class="relative w-full max-w-sm">
          <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
            <x-svg.search class="h-4 w-4" />
          </span>
          <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name, username, email..."
            class="w-full rounded-xl border border-neutral-border bg-white py-3 pl-11 pr-4 text-sm text-gray-800 placeholder-neutral-label outline-none transition focus:border-accent-primary focus:ring-1 focus:ring-accent-primary dark:border-gray-800 dark:bg-white/[0.03] dark:text-white dark:focus:border-blue-500" />
        </div>

        <!-- Figma style Filter Block -->
        <div
          class="flex flex-col gap-4 rounded-[16px] border border-neutral-border bg-neutral-bg p-4 md:flex-row md:items-end md:justify-between dark:border-gray-800 dark:bg-white/[0.02]">
          <div class="flex flex-1 flex-wrap items-center gap-4">
            <!-- Role filter -->
            <div class="flex min-w-[200px] flex-1 flex-col gap-1.5">
              <label class="text-sm font-normal text-neutral-label dark:text-gray-400">Role</label>
              <div class="relative">
                <select wire:model="tempFilterRole"
                  class="w-full appearance-none rounded-xl border border-neutral-border bg-white px-4 py-3 text-sm text-neutral-label outline-none transition focus:border-accent-primary dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                  <option value="">All</option>
                  @foreach ($this->allRoles as $role)
                    <option value="{{ $role->name }}">{{ $role->name }}</option>
                  @endforeach
                </select>
                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-gray-500">
                  <x-svg.chevron-down class="h-4 w-4" />
                </span>
              </div>
            </div>

            <!-- Status filter -->
            <div class="flex min-w-[200px] flex-1 flex-col gap-1.5">
              <label class="text-sm font-normal text-neutral-label dark:text-gray-400">Status</label>
              <div class="relative">
                <select wire:model="tempFilterStatus"
                  class="w-full appearance-none rounded-xl border border-neutral-border bg-white px-4 py-3 text-sm text-neutral-label outline-none transition focus:border-accent-primary dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                  <option value="">All</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-gray-500">
                  <x-svg.chevron-down class="h-4 w-4" />
                </span>
              </div>
            </div>
          </div>

          <!-- Filter Actions -->
          <div class="flex items-center gap-2">
            <button wire:click="applyFilters"
              class="h-[48px] min-w-[120px] rounded-xl bg-accent-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-accent-primary-hover">
              Apply
            </button>
            <button wire:click="resetFilters"
              class="flex h-[48px] w-[48px] items-center justify-center rounded-xl border border-neutral-border bg-gray-100 p-3 text-gray-700 transition hover:bg-gray-200 dark:border-gray-800 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10"
              title="Reset Filters">
              <x-svg.undo class="h-5 w-5" />
            </button>
          </div>
        </div>
      </div>

      <!-- Users Table -->
      <div
        class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="custom-scrollbar max-w-full overflow-x-auto">
          <table class="w-full min-w-[900px] font-sans">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50/50 dark:border-gray-800 dark:bg-white/[0.01]">
                <x-tables.th-sortable column="id" :sortColumn="$sortColumn" :sortDirection="$sortDirection" class="w-[100px]">
                  ID
                </x-tables.th-sortable>
                <x-tables.th-sortable column="name" :sortColumn="$sortColumn" :sortDirection="$sortDirection">
                  User
                </x-tables.th-sortable>
                <x-tables.th-sortable column="email" :sortColumn="$sortColumn" :sortDirection="$sortDirection">
                  Email
                </x-tables.th-sortable>
                <x-tables.th-sortable column="role" :sortColumn="$sortColumn" :sortDirection="$sortDirection" align="center">
                  Role
                </x-tables.th-sortable>
                <x-tables.th-sortable column="status" :sortColumn="$sortColumn" :sortDirection="$sortDirection" align="center">
                  Status
                </x-tables.th-sortable>
                <x-tables.th-sortable :sortable="false" align="center">
                  Action
                </x-tables.th-sortable>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
              @forelse($this->users as $user)
                <tr wire:key="user-{{ $user->id }}" class="transition-colors hover:bg-gray-50/30 dark:hover:bg-white/[0.01]">
                  <!-- ID -->
                  <td class="whitespace-nowrap px-6 py-4">
                    <span class="text-sm font-semibold text-neutral-heading dark:text-white/90">
                      #{{ $user->id }}
                    </span>
                  </td>
                  <!-- User name & avatar -->
                  <td class="whitespace-nowrap px-6 py-4">
                    <div class="flex items-center gap-3">
                      <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-accent-primary/10 text-sm font-semibold text-accent-primary dark:bg-blue-600/10 dark:text-blue-400">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                      </div>
                      <div>
                        <span class="block text-sm font-semibold text-gray-800 dark:text-white/90">
                          {{ $user->name }}
                        </span>
                      </div>
                    </div>
                  </td>
                  <!-- Email -->
                  <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                    {{ $user->email }}
                  </td>
                  <!-- Role -->
                  <td class="whitespace-nowrap px-6 py-4 text-center text-sm text-gray-600 dark:text-gray-300">
                    @forelse($user->roles as $role)
                      <span
                        class="inline-flex rounded-lg bg-accent-primary/5 px-2.5 py-1 text-xs font-semibold text-accent-primary dark:bg-blue-600/10 dark:text-blue-400">
                        {{ $role->name }}
                      </span>
                    @empty
                      <span class="text-xs italic text-gray-400">No Role</span>
                    @endforelse
                  </td>
                  <!-- Status -->
                  <td class="whitespace-nowrap px-6 py-4 text-center">
                    @if ($user->status === 'active')
                      <div
                        class="relative inline-flex content-stretch items-center justify-center rounded-[12px] bg-success-200 px-[10px] py-[6px]">
                        <p
                          class="relative shrink-0 whitespace-nowrap text-center text-[14px] font-semibold not-italic leading-[20px] text-success-500 [word-break:break-word]">
                          Active
                        </p>
                      </div>
                    @else
                      <div
                        class="relative inline-flex content-stretch items-center justify-center rounded-[12px] bg-gray-100 px-[10px] py-[6px]">
                        <p
                          class="relative shrink-0 whitespace-nowrap text-center text-[14px] font-semibold not-italic leading-[20px] text-gray-500 [word-break:break-word]">
                          Inactive
                        </p>
                      </div>
                    @endif
                  </td>
                  <!-- Action -->
                  <td class="whitespace-nowrap px-6 py-4 text-center">
                    <div class="flex items-center justify-center gap-3">
                      <!-- Detail Button -->
                      <button wire:click="$dispatch('open-user-detail', { userId: {{ $user->id }} })"
                        class="p-1 text-gray-500 transition-colors hover:text-accent-primary dark:text-gray-400 dark:hover:text-blue-400"
                        title="Detail">
                        <x-svg.eye class="h-5 w-5" />
                      </button>

                      <!-- Edit Button -->
                      <button wire:click="$dispatch('open-user-form', { id: {{ $user->id }} })"
                        class="p-1 text-gray-500 transition-colors hover:text-amber-600 dark:text-gray-400 dark:hover:text-amber-400"
                        title="Edit">
                        <x-svg.pencil class="h-5 w-5" />
                      </button>

                      <!-- Deactivate Button -->
                      <button wire:click="$dispatch('open-user-delete', { id: {{ $user->id }}, name: '{{ addslashes($user->name) }}', username: '{{ addslashes($user->username) }}' })"
                        class="p-1 text-gray-500 transition-colors hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                        title="Deactivate">
                        <x-svg.trash class="h-5 w-5" />
                      </button>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6"
                    class="px-6 py-10 text-center italic text-gray-400 dark:text-gray-500">
                    No users found matching your criteria.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination Block -->
      <div
        class="flex flex-col items-center justify-between gap-4 border-t border-gray-100 pt-6 md:flex-row dark:border-gray-800">
        <!-- Entries Selector -->
        <div class="flex items-center gap-3">
          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Show</span>
          <div class="relative">
            <select wire:model.live="perPage"
              class="focus:outline-hidden appearance-none rounded-xl border border-gray-300 bg-white py-2 pl-3 pr-8 text-sm text-gray-800 focus:border-accent-primary dark:border-gray-700 dark:bg-gray-900 dark:text-white">
              <option value="5">5</option>
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
            </select>
            <span
              class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
              <x-svg.chevron-down class="h-4 w-4" />
            </span>
          </div>
          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">entries</span>
        </div>

        <!-- Page Info / Navigation -->
        @php
          $paginator = $this->users;
        @endphp
        @if ($paginator->hasPages())
          <div class="flex items-center justify-center gap-1.5">
            <button wire:click="previousPage" @if ($paginator->onFirstPage()) disabled @endif
              class="flex h-10 w-10 items-center justify-center rounded-xl border border-gray-300 bg-white text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
              <x-svg.chevron-left class="h-4 w-4" />
            </button>

            @php
              $currentPage = $paginator->currentPage();
              $lastPage = $paginator->lastPage();
              $startPage = max(2, $currentPage - 1);
              $endPage = min($lastPage - 1, $currentPage + 1);
            @endphp

            @if ($currentPage == 1)
              <div
                class="flex h-10 w-10 cursor-default items-center justify-center rounded-xl border-[0.8px] border-solid border-accent-primary bg-accent-primary px-4 py-2">
                <p class="text-[14px] font-semibold text-white">1</p>
              </div>
            @else
              <button wire:click="gotoPage(1)"
                class="flex h-10 w-10 items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-neutral-label transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                1
              </button>
            @endif

            @if ($startPage > 2)
              <span class="flex h-10 w-10 items-center justify-center text-gray-400">...</span>
            @endif

            @for ($i = $startPage; $i <= $endPage; $i++)
              @if ($i == $currentPage)
                <div
                  class="flex h-10 w-10 cursor-default items-center justify-center rounded-xl border-[0.8px] border-solid border-accent-primary bg-accent-primary px-4 py-2">
                  <p class="text-[14px] font-semibold text-white">{{ $i }}</p>
                </div>
              @else
                <button wire:click="gotoPage({{ $i }})"
                  class="flex h-10 w-10 items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-neutral-label transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                  {{ $i }}
                </button>
              @endif
            @endfor

            @if ($endPage < $lastPage - 1)
              <span class="flex h-10 w-10 items-center justify-center text-gray-400">...</span>
            @endif

            @if ($lastPage > 1)
              @if ($currentPage == $lastPage)
                <div
                  class="flex h-10 w-10 cursor-default items-center justify-center rounded-xl border-[0.8px] border-solid border-accent-primary bg-accent-primary px-4 py-2">
                  <p class="text-[14px] font-semibold text-white">{{ $lastPage }}</p>
                </div>
              @else
                <button wire:click="gotoPage({{ $lastPage }})"
                  class="flex h-10 w-10 items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-neutral-label transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                  {{ $lastPage }}
                </button>
              @endif
            @endif

            <button wire:click="nextPage" @if (!$paginator->hasMorePages()) disabled @endif
              class="flex h-10 w-10 items-center justify-center rounded-xl border border-gray-300 bg-white text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
              <x-svg.chevron-right class="h-4 w-4" />
            </button>
          </div>
        @endif
      </div>

    </div>

  </div>

  <!-- Modular Modals -->
  <livewire:rbac.user-form-modal />
  <livewire:rbac.user-detail-modal />
  <livewire:rbac.user-delete-modal />
</div>
