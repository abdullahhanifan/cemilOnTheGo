@php
  use App\Helpers\MenuHelper;
  $menuGroups = MenuHelper::getFilteredMenuGroups();

  // Get current path
  $currentPath = request()->path();
@endphp

<aside id="sidebar"
  class="z-99999 fixed left-0 top-0 mt-0 flex h-screen w-[90px] flex-col border-r border-neutral-border bg-neutral-card px-5 text-gray-900 transition-all duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900 [.sidebar-expanded_&]:w-[300px]"
  x-data="{
      openSubmenus: {},
      currentPath: window.location.pathname,
      init() {
          // Auto-open Dashboard menu on page load
          this.initializeActiveMenus();

          // Use a named handler so it can be removed before re-adding,
          // preventing duplicate listeners from stacking on every wire:navigate.
          this._navigatedHandler = () => {
              this.currentPath = window.location.pathname;
              this.initializeActiveMenus();
          };
          document.removeEventListener('livewire:navigated', this._navigatedHandler);
          document.addEventListener('livewire:navigated', this._navigatedHandler);
      },
      initializeActiveMenus() {
          const currentPath = this.currentPath;

          @foreach($menuGroups as $groupIndex => $menuGroup)
          @foreach($menuGroup['items'] as $itemIndex => $item)
          @if(isset($item['subItems']))
          // Check if any submenu item matches current path
          @foreach($item['subItems'] as $subItem)
          if (currentPath === '{{ $subItem['path'] }}' ||
              currentPath === '{{ ltrim($subItem['path'], '/') }}') {
              this.openSubmenus['{{ $groupIndex }}-{{ $itemIndex }}'] = true;
          }
          @endforeach
          @endif
          @endforeach
          @endforeach
      },
      toggleSubmenu(groupIndex, itemIndex) {
          const key = groupIndex + '-' + itemIndex;
          const newState = !this.openSubmenus[key];

          // Close all other submenus when opening a new one
          if (newState) {
              this.openSubmenus = {};
          }

          this.openSubmenus[key] = newState;
      },
      isSubmenuOpen(groupIndex, itemIndex) {
          const key = groupIndex + '-' + itemIndex;
          return this.openSubmenus[key] || false;
      },
      isActive(path) {
          return this.currentPath === path || this.currentPath.replace(/^\//, '') === path.replace(/^\//, '');
      }
  }"
  :class="{
      'translate-x-0': $store.sidebar.isMobileOpen,
      '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen
  }"
  @mouseenter="if (!$store.sidebar.isExpanded) $store.sidebar.setHovered(true)"
  @mouseleave="$store.sidebar.setHovered(false)">

  <!-- Desktop Sidebar Toggle Button (floating chevron on sidebar border) -->
  <button @click.stop="$store.sidebar.toggleExpanded()"
    class="z-99999 absolute -right-3.5 top-[25px] hidden h-7 w-7 items-center justify-center rounded-full bg-accent-primary text-white shadow-md transition hover:bg-accent-primary-hover xl:flex"
    aria-label="Toggle Sidebar">
    <svg class="h-3.5 w-3.5 transition-transform duration-300" :class="{ 'rotate-180': !$store.sidebar.isExpanded }"
      fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
    </svg>
  </button>

  <!-- Logo Section -->
  <div class="mb-6 flex items-center justify-center pb-4 pt-4"
    :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'px-0' : 'px-8'">
    <a href="/" wire:navigate class="flex items-center justify-center">
      <div class="hidden [.sidebar-expanded_&]:block">
        <img class="h-14 w-auto" src="{{ asset('images/brand/logo.svg') }}" alt="{{ config('app.name') }}" />
      </div>
      <img class="block h-12 w-auto [.sidebar-expanded_&]:hidden" src="{{ asset('images/brand/logo-icon.svg') }}" alt="{{ config('app.name') }}" />
    </a>
  </div>

  <!-- Navigation Menu -->
  <div class="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear" wire:navigate:scroll>
    <nav class="mb-6">
      <div class="flex flex-col gap-4">
        @foreach ($menuGroups as $groupIndex => $menuGroup)
          <div>
            <!-- Menu Group Title -->
            <h2 class="mb-4 flex text-base font-normal leading-[24px] text-neutral-label dark:text-gray-400"
              :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
              'lg:justify-center' : 'justify-start px-3'">
              <template x-if="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">
                <span>{{ $menuGroup['title'] }}</span>
              </template>
              <template x-if="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                  xmlns="http://www.w3.org/2000/svg">
                  <path fill-rule="evenodd" clip-rule="evenodd"
                    d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z"
                    fill="currentColor" />
                </svg>
              </template>
            </h2>

            <!-- Menu Items -->
            <ul class="flex flex-col gap-1">
              @foreach ($menuGroup['items'] as $itemIndex => $item)
                <li>
                  @if (isset($item['subItems']))
                    <!-- Menu Item with Submenu -->
                    <button @click="toggleSubmenu({{ $groupIndex }}, {{ $itemIndex }})"
                      class="menu-item group w-full"
                      :class="[
                          isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ?
                          'menu-item-active' : 'menu-item-inactive',
                          !$store.sidebar.isExpanded && !$store.sidebar.isHovered ?
                          'xl:justify-center' : 'xl:justify-start'
                      ]">

                      <!-- Icon -->
                      <span
                        :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ?
                            'menu-item-icon-active' : 'menu-item-icon-inactive'">
                        <x-dynamic-component :component="'svg.' . $item['icon']" />
                      </span>

                      <!-- Text -->
                      <span
                        x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                        class="menu-item-text flex items-center gap-2">
                        {{ $item['name'] }}
                        @if (!empty($item['new']))
                          <span class="absolute right-10"
                            :class="isActive('{{ $item['path'] ?? '' }}') ?
                                'menu-dropdown-badge menu-dropdown-badge-active' :
                                'menu-dropdown-badge menu-dropdown-badge-inactive'">
                            new
                          </span>
                        @endif
                      </span>

                      <!-- Chevron Down Icon -->
                      <svg x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                        class="ml-auto h-5 w-5 transition-transform duration-200"
                        :class="{
                            'rotate-180 text-brand-500': isSubmenuOpen({{ $groupIndex }},
                                {{ $itemIndex }})
                        }"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                      </svg>
                    </button>

                    <!-- Submenu -->
                    <div
                      x-show="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) && ($store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen)">
                      <ul class="ml-9 mt-2 space-y-1">
                        @foreach ($item['subItems'] as $subItem)
                          <li>
                            <a href="{{ $subItem['path'] }}"
                              @if ($subItem['livewire'] ?? true) wire:navigate @endif
                              class="menu-dropdown-item"
                              :class="isActive('{{ $subItem['path'] }}') ?
                                  'menu-dropdown-item-active' :
                                  'menu-dropdown-item-inactive'">
                              {{ $subItem['name'] }}
                              <span class="ml-auto flex items-center gap-1">
                                @if (!empty($subItem['new']))
                                  <span
                                    :class="isActive('{{ $subItem['path'] }}') ?
                                        'menu-dropdown-badge menu-dropdown-badge-active' :
                                        'menu-dropdown-badge menu-dropdown-badge-inactive'">
                                    new
                                  </span>
                                @endif
                              </span>
                            </a>
                          </li>
                        @endforeach
                      </ul>
                    </div>
                  @else
                    <!-- Simple Menu Item -->
                    <a href="{{ $item['path'] }}"
                      @if ($item['livewire'] ?? true) wire:navigate @endif
                      class="menu-item group"
                      :class="[
                          isActive('{{ $item['path'] }}') ? 'menu-item-active' :
                          'menu-item-inactive',
                          (!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
                          'xl:justify-center' :
                          'justify-start'
                      ]">

                      <!-- Icon -->
                      <span
                        :class="isActive('{{ $item['path'] }}') ? 'menu-item-icon-active' :
                            'menu-item-icon-inactive'">
                        <x-dynamic-component :component="'svg.' . $item['icon']" />
                      </span>

                      <!-- Text -->
                      <span
                        x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                        class="menu-item-text flex items-center gap-2">
                        {{ $item['name'] }}
                        @if (!empty($item['new']))
                          <span
                            class="bg-brand-500 ml-2 inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold text-white">
                            new
                          </span>
                        @endif
                      </span>
                    </a>
                  @endif
                </li>
              @endforeach
            </ul>
          </div>
        @endforeach
      </div>
    </nav>



  </div>
</aside>

<!-- Mobile Overlay -->
<div x-show="$store.sidebar.isMobileOpen" @click="$store.sidebar.setMobileOpen(false)"
  class="fixed z-50 h-screen w-full bg-gray-900/50" x-cloak></div>
