<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Store;

new #[Layout('layouts.app')] #[Title('Daftar Produk')] class extends Component {
    use WithPagination;

    /** Columns the table may be sorted by; anything else falls back to id. */
    private const SORTABLE_COLUMNS = ['id', 'name', 'buy_price', 'sell_price', 'status'];

    public function mount()
    {
        abort_unless(auth()->user()?->can('product:product-view'), 403);
    }

    public string $search = '';
    public string $filterStore = '';
    public string $filterStatus = '';

    public string $tempFilterStore = '';
    public string $tempFilterStatus = '';

    public int $perPage = 10;
    public string $sortColumn = 'id';
    public string $sortDirection = 'asc';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStore' => ['except' => ''],
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
     * Every store, for the filter dropdown (deactivated stores keep their products).
     */
    #[Computed]
    public function stores()
    {
        return Store::query()->orderBy('name')->get(['id', 'name', 'city']);
    }

    #[Computed]
    public function products()
    {
        $sortColumn = in_array($this->sortColumn, self::SORTABLE_COLUMNS, true) ? $this->sortColumn : 'id';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $query = Product::query()
            ->with('store:id,name,city,area,status')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('variant', 'like', '%' . $this->search . '%')
                        ->orWhere('sku', 'like', '%' . $this->search . '%')
                        ->orWhereHas('store', function ($store) {
                            $store->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when(ctype_digit($this->filterStore), function ($query) {
                $query->where('store_id', (int) $this->filterStore);
            })
            ->when($this->filterStatus, function ($query) {
                $query->where('status', $this->filterStatus);
            })
            ->orderBy($sortColumn, $sortDirection);

        return $query->paginate($this->perPage);
    }

    public function applyFilters()
    {
        $this->filterStore = $this->tempFilterStore;
        $this->filterStatus = $this->tempFilterStatus;
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->tempFilterStore = '';
        $this->tempFilterStatus = '';
        $this->filterStore = '';
        $this->filterStatus = '';
        $this->resetPage();
    }

    #[On('product-saved')]
    public function refreshProducts()
    {
        unset($this->products);
    }

    #[On('product-deactivated')]
    public function refreshProductsAfterDeactivate()
    {
        unset($this->products);
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
            Daftar Produk
          </h3>
          <p class="mt-1 text-sm text-neutral-label">
            Kelola produk per toko beserta harga beli, harga jual, dan estimasi margin
          </p>
        </div>

        <div class="flex items-center gap-3">
          <button wire:click="$dispatch('open-product-form')"
            class="inline-flex items-center gap-2 rounded-xl bg-accent-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-accent-primary-hover">
            <x-svg.plus class="h-4 w-4" />
            <span>Tambah Produk</span>
          </button>
        </div>
      </div>

      <!-- Search & Filters -->
      <div class="mb-6 space-y-4">
        <div class="relative w-full max-w-sm">
          <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
            <x-svg.search class="h-4 w-4" />
          </span>
          <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari produk, varian, kode, toko..."
            class="w-full rounded-xl border border-neutral-border bg-white py-3 pl-11 pr-4 text-sm text-gray-800 placeholder-neutral-label outline-none transition focus:border-accent-primary focus:ring-1 focus:ring-accent-primary dark:border-gray-800 dark:bg-white/[0.03] dark:text-white" />
        </div>

        <div
          class="flex flex-col gap-4 rounded-[16px] border border-neutral-border bg-neutral-bg p-4 md:flex-row md:items-end md:justify-between dark:border-gray-800 dark:bg-white/[0.02]">
          <div class="flex flex-1 flex-wrap items-center gap-4">
            <div class="flex min-w-[200px] flex-1 flex-col gap-1.5">
              <label class="text-sm font-normal text-neutral-label dark:text-gray-400">Toko</label>
              <div class="relative">
                <select wire:model="tempFilterStore"
                  class="w-full appearance-none rounded-xl border border-neutral-border bg-white px-4 py-3 text-sm text-neutral-label outline-none transition focus:border-accent-primary dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                  <option value="">Semua</option>
                  @foreach ($this->stores as $store)
                    <option value="{{ $store->id }}">{{ $store->name }} — {{ $store->city }}</option>
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
          <table class="w-full min-w-[1100px] font-sans">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50/50 dark:border-gray-800 dark:bg-white/[0.01]">
                <x-tables.th-sortable column="id" :sortColumn="$sortColumn" :sortDirection="$sortDirection" class="w-[80px]">
                  ID
                </x-tables.th-sortable>
                <x-tables.th-sortable column="name" :sortColumn="$sortColumn" :sortDirection="$sortDirection">
                  Produk
                </x-tables.th-sortable>
                <x-tables.th-sortable :sortable="false">
                  Toko
                </x-tables.th-sortable>
                <x-tables.th-sortable column="buy_price" :sortColumn="$sortColumn" :sortDirection="$sortDirection">
                  Harga Beli
                </x-tables.th-sortable>
                <x-tables.th-sortable column="sell_price" :sortColumn="$sortColumn" :sortDirection="$sortDirection">
                  Harga Jual
                </x-tables.th-sortable>
                <x-tables.th-sortable :sortable="false">
                  Estimasi Margin
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
              @forelse ($this->products as $product)
                @php
                  $margin = $product->estimatedMargin();
                  $marginPercent = $product->estimatedMarginPercent();
                @endphp
                <tr wire:key="product-{{ $product->id }}" class="transition-colors hover:bg-gray-50/30 dark:hover:bg-white/[0.01]">
                  <td class="whitespace-nowrap px-6 py-4">
                    <span class="text-sm font-semibold text-neutral-heading dark:text-white/90">#{{ $product->id }}</span>
                  </td>
                  <td class="whitespace-nowrap px-6 py-4">
                    <span class="block text-sm font-semibold text-gray-800 dark:text-white/90">{{ $product->name }}</span>
                    @if ($product->variant !== '')
                      <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $product->variant }}</span>
                    @endif
                    <span class="block text-xs text-gray-400">
                      Satuan: {{ $product->unit }}@if ($product->sku) · Kode: {{ $product->sku }}@endif
                    </span>
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                    <span class="block">{{ $product->store->name }}</span>
                    <span class="block text-xs text-gray-400">
                      {{ $product->store->city }}@if ($product->store->status === 'inactive') · Toko nonaktif @endif
                    </span>
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                    <span class="block">{{ \App\Helpers\Rupiah::format($product->buy_price) }}</span>
                    <span class="block text-xs text-gray-400">
                      @if ($product->buy_price_checked_at)
                        Dicek {{ $product->buy_price_checked_at->locale('id')->diffForHumans() }}
                      @else
                        Belum pernah dicek
                      @endif
                    </span>
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                    {{ \App\Helpers\Rupiah::format($product->sell_price) }}
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-sm {{ $margin < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-300' }}">
                    {{ \App\Helpers\Rupiah::format($margin) }}
                    @if ($marginPercent !== null)
                      <span class="block text-xs">{{ number_format($marginPercent, 1, ',', '.') }}%</span>
                    @endif
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-center">
                    @if ($product->status === 'active')
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
                      <button wire:click="$dispatch('open-product-form', { id: {{ $product->id }} })"
                        class="p-1 text-gray-500 transition-colors hover:text-amber-600 dark:text-gray-400 dark:hover:text-amber-400"
                        title="Edit">
                        <x-svg.pencil class="h-5 w-5" />
                      </button>
                      @if ($product->status === 'active')
                        <button wire:click="$dispatch('open-product-deactivate', { id: {{ $product->id }}, name: '{{ addslashes($product->name) }}' })"
                          class="p-1 text-gray-500 transition-colors hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                          title="Nonaktifkan">
                          <x-svg.trash class="h-5 w-5" />
                        </button>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="px-6 py-10 text-center italic text-gray-400 dark:text-gray-500">
                    Belum ada data produk.
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

        @php $paginator = $this->products; @endphp
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

  <livewire:product.product-form-modal />
  <livewire:product.product-deactivate-modal />
</div>
