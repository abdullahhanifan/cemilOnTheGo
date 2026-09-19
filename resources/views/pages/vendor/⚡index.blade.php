<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Vendor;

new #[Layout('layouts.app')] #[Title('Data Vendor')] class extends Component {
    use WithPagination;

    public function mount()
    {
        abort_unless(auth()->user()?->can('vendor:vendor-view'), 403);
    }

    public string $search = '';
    public string $filterCategory = '';
    public string $filterStatus = '';

    public string $tempFilterCategory = '';
    public string $tempFilterStatus = '';

    public int $perPage = 10;
    public string $sortColumn = 'id';
    public string $sortDirection = 'asc';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterCategory' => ['except' => ''],
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

    /**
     * Distinct categories currently in use, for the filter dropdown.
     */
    #[Computed]
    public function categories()
    {
        return Vendor::whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }

    #[Computed]
    public function vendors()
    {
        $query = Vendor::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('contact_name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterCategory, function ($query) {
                $query->where('category', $this->filterCategory);
            })
            ->when($this->filterStatus, function ($query) {
                $query->where('status', $this->filterStatus);
            })
            ->orderBy($this->sortColumn, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    public function applyFilters()
    {
        $this->filterCategory = $this->tempFilterCategory;
        $this->filterStatus = $this->tempFilterStatus;
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->tempFilterCategory = '';
        $this->tempFilterStatus = '';
        $this->filterCategory = '';
        $this->filterStatus = '';
        $this->resetPage();
    }

    #[On('vendor-saved')]
    public function refreshVendors()
    {
        unset($this->vendors);
    }

    #[On('vendor-deleted')]
    public function refreshVendorsAfterDelete()
    {
        unset($this->vendors);
        $this->resetPage();
    }
};
?>

<div class="">
  <div class="space-y-6">
    <div class="rounded-2xl border border-neutral-border bg-neutral-card p-5 xl:p-8">

      <!-- Header -->
      <div class="mb-6 flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
        <div>
          <h3 class="text-2xl font-bold tracking-tight text-neutral-heading">
            Data Vendor
          </h3>
          <p class="mt-1 text-sm text-neutral-label">
            Kelola daftar vendor beserta kontak dan statusnya
          </p>
        </div>

        <div class="flex items-center gap-3">
          <button wire:click="$dispatch('open-vendor-form')"
            class="inline-flex items-center gap-2 rounded-xl bg-accent-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-accent-primary-hover">
            <x-svg.plus class="h-4 w-4" />
            <span>Tambah Vendor</span>
          </button>
        </div>
      </div>

      <!-- Search & Filters -->
      <div class="mb-6 space-y-4">
        <div class="relative w-full max-w-sm">
          <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
            <x-svg.search class="h-4 w-4" />
          </span>
          <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama, kontak, email..."
            class="w-full rounded-xl border border-neutral-border bg-white py-3 pl-11 pr-4 text-sm text-gray-800 placeholder-neutral-label outline-none transition focus:border-accent-primary focus:ring-1 focus:ring-accent-primary dark:border-gray-800 dark:bg-white/[0.03] dark:text-white" />
        </div>

        <div
          class="flex flex-col gap-4 rounded-[16px] border border-neutral-border bg-neutral-bg p-4 md:flex-row md:items-end md:justify-between dark:border-gray-800 dark:bg-white/[0.02]">
          <div class="flex flex-1 flex-wrap items-center gap-4">
            <div class="flex min-w-[200px] flex-1 flex-col gap-1.5">
              <label class="text-sm font-normal text-neutral-label dark:text-gray-400">Kategori</label>
              <div class="relative">
                <select wire:model="tempFilterCategory"
                  class="w-full appearance-none rounded-xl border border-neutral-border bg-white px-4 py-3 text-sm text-neutral-label outline-none transition focus:border-accent-primary dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                  <option value="">Semua</option>
                  @foreach ($this->categories as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                  @endforeach
                </select>
                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-gray-500">
                  <x-svg.chevron-down class="h-4 w-4" />
                </span>
              </div>
            </div>

            <div class="flex min-w-[200px] flex-1 flex-col gap-1.5">
              <label class="text-sm font-normal text-neutral-label dark:text-gray-400">Status</label>
              <div class="relative">
                <select wire:model="tempFilterStatus"
                  class="w-full appearance-none rounded-xl border border-neutral-border bg-white px-4 py-3 text-sm text-neutral-label outline-none transition focus:border-accent-primary dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                  <option value="">Semua</option>
                  <option value="active">Aktif</option>
                  <option value="inactive">Nonaktif</option>
                </select>
                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-gray-500">
                  <x-svg.chevron-down class="h-4 w-4" />
                </span>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button wire:click="applyFilters"
              class="h-[48px] min-w-[120px] rounded-xl bg-accent-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-accent-primary-hover">
              Terapkan
            </button>
            <button wire:click="resetFilters"
              class="flex h-[48px] w-[48px] items-center justify-center rounded-xl border border-neutral-border bg-gray-100 p-3 text-gray-700 transition hover:bg-gray-200 dark:border-gray-800 dark:bg-white/5 dark:text-gray-300"
              title="Reset Filter">
              <x-svg.undo class="h-5 w-5" />
            </button>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="mb-6 overflow-hidden rounded-xl border border-neutral-border bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="custom-scrollbar max-w-full overflow-x-auto">
          <table class="w-full min-w-[900px] font-sans">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50/50 dark:border-gray-800 dark:bg-white/[0.01]">
                <x-tables.th-sortable column="id" :sortColumn="$sortColumn" :sortDirection="$sortDirection" class="w-[80px]">
                  ID
                </x-tables.th-sortable>
                <x-tables.th-sortable column="name" :sortColumn="$sortColumn" :sortDirection="$sortDirection">
                  Vendor
                </x-tables.th-sortable>
                <x-tables.th-sortable :sortable="false">
                  Kontak
                </x-tables.th-sortable>
                <x-tables.th-sortable column="category" :sortColumn="$sortColumn" :sortDirection="$sortDirection">
                  Kategori
                </x-tables.th-sortable>
                <x-tables.th-sortable column="status" :sortColumn="$sortColumn" :sortDirection="$sortDirection" align="center">
                  Status
                </x-tables.th-sortable>
                <x-tables.th-sortable :sortable="false" align="center">
                  Aksi
                </x-tables.th-sortable>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
              @forelse ($this->vendors as $vendor)
                <tr wire:key="vendor-{{ $vendor->id }}" class="transition-colors hover:bg-gray-50/30 dark:hover:bg-white/[0.01]">
                  <td class="whitespace-nowrap px-6 py-4">
                    <span class="text-sm font-semibold text-neutral-heading dark:text-white/90">#{{ $vendor->id }}</span>
                  </td>
                  <td class="whitespace-nowrap px-6 py-4">
                    <span class="block text-sm font-semibold text-gray-800 dark:text-white/90">{{ $vendor->name }}</span>
                    @if ($vendor->address)
                      <span class="block max-w-xs truncate text-xs text-gray-400">{{ $vendor->address }}</span>
                    @endif
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                    @if ($vendor->contact_name)
                      <span class="block">{{ $vendor->contact_name }}</span>
                    @endif
                    @if ($vendor->phone)
                      <span class="block text-xs text-gray-400">{{ $vendor->phone }}</span>
                    @endif
                    @if ($vendor->email)
                      <span class="block text-xs text-gray-400">{{ $vendor->email }}</span>
                    @endif
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                    {{ $vendor->category ?? '-' }}
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-center">
                    @if ($vendor->status === 'active')
                      <div class="relative inline-flex content-stretch items-center justify-center rounded-[12px] bg-success-200 px-[10px] py-[6px]">
                        <p class="relative shrink-0 whitespace-nowrap text-center text-[14px] font-semibold not-italic leading-[20px] text-success-500">Aktif</p>
                      </div>
                    @else
                      <div class="relative inline-flex content-stretch items-center justify-center rounded-[12px] bg-gray-100 px-[10px] py-[6px]">
                        <p class="relative shrink-0 whitespace-nowrap text-center text-[14px] font-semibold not-italic leading-[20px] text-gray-500">Nonaktif</p>
                      </div>
                    @endif
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-center">
                    <div class="flex items-center justify-center gap-3">
                      <button wire:click="$dispatch('open-vendor-form', { id: {{ $vendor->id }} })"
                        class="p-1 text-gray-500 transition-colors hover:text-amber-600 dark:text-gray-400 dark:hover:text-amber-400"
                        title="Edit">
                        <x-svg.pencil class="h-5 w-5" />
                      </button>
                      <button wire:click="$dispatch('open-vendor-delete', { id: {{ $vendor->id }}, name: '{{ addslashes($vendor->name) }}' })"
                        class="p-1 text-gray-500 transition-colors hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                        title="Hapus">
                        <x-svg.trash class="h-5 w-5" />
                      </button>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="px-6 py-10 text-center italic text-gray-400 dark:text-gray-500">
                    Belum ada data vendor.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div class="flex flex-col items-center justify-between gap-4 border-t border-gray-100 pt-6 md:flex-row dark:border-gray-800">
        <div class="flex items-center gap-3">
          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Tampilkan</span>
          <div class="relative">
            <select wire:model.live="perPage"
              class="focus:outline-hidden appearance-none rounded-xl border border-gray-300 bg-white py-2 pl-3 pr-8 text-sm text-gray-800 focus:border-accent-primary dark:border-gray-700 dark:bg-gray-900 dark:text-white">
              <option value="5">5</option>
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
            </select>
            <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
              <x-svg.chevron-down class="h-4 w-4" />
            </span>
          </div>
          <span class="text-sm font-medium text-gray-500 dark:text-gray-400">data</span>
        </div>

        @php $paginator = $this->vendors; @endphp
        @if ($paginator->hasPages())
          <div class="flex items-center justify-center gap-1.5">
            <button wire:click="previousPage" @if ($paginator->onFirstPage()) disabled @endif
              class="flex h-10 w-10 items-center justify-center rounded-xl border border-gray-300 bg-white text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
              <x-svg.chevron-left class="h-4 w-4" />
            </button>
            <span class="px-3 text-sm font-medium text-gray-600 dark:text-gray-300">
              Halaman {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>
            <button wire:click="nextPage" @if (!$paginator->hasMorePages()) disabled @endif
              class="flex h-10 w-10 items-center justify-center rounded-xl border border-gray-300 bg-white text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
              <x-svg.chevron-right class="h-4 w-4" />
            </button>
          </div>
        @endif
      </div>
    </div>
  </div>

  <livewire:vendor.vendor-form-modal />
  <livewire:vendor.vendor-delete-modal />
</div>
