@props([
    'column' => null,
    'sortColumn' => null,
    'sortDirection' => 'asc',
    'align' => 'left',
    'sortable' => true,
    'wireAction' => 'sortBy',
])

@php
  $isSortable = $sortable && !empty($column);
  $isSorted = $isSortable && $sortColumn === $column;

  $alignContainerClass = match ($align) {
      'center' => 'justify-center text-center',
      'right' => 'justify-end text-right',
      default => 'justify-start text-left',
  };

  $alignThClass = match ($align) {
      'center' => 'text-center',
      'right' => 'text-right',
      default => 'text-left',
  };
@endphp

<th {{ $attributes->merge(['class' => 'px-6 py-4 text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 select-none ' . $alignThClass]) }}>
  @if ($isSortable)
    <button 
      type="button" 
      wire:click="{{ $wireAction }}('{{ $column }}')" 
      class="group inline-flex items-center gap-1.5 font-bold transition-colors hover:text-gray-900 dark:hover:text-white {{ $alignContainerClass }} w-full focus:outline-none"
    >
      <span class="font-bold tracking-wider">{{ $slot }}</span>
      <span class="inline-flex shrink-0">
        @if ($isSorted)
          @if ($sortDirection === 'asc')
            {{-- Up Arrow --}}
            <svg class="h-4 w-4 text-accent-primary dark:text-blue-400 font-bold" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
            </svg>
          @else
            {{-- Down Arrow --}}
            <svg class="h-4 w-4 text-accent-primary dark:text-blue-400 font-bold" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          @endif
        @else
          {{-- Dual Neutral Arrow --}}
          <svg class="h-4 w-4 text-gray-400 opacity-40 group-hover:opacity-100 transition-opacity dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
          </svg>
        @endif
      </span>
    </button>
  @else
    <span class="font-bold tracking-wider">{{ $slot }}</span>
  @endif
</th>
