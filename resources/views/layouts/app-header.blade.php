@php
  $currentPath = request()->path();
  $groupTitle = '';
  $itemTitle = '';

  foreach (\App\Helpers\MenuHelper::getFilteredMenuGroups() as $group) {
      foreach ($group['items'] as $item) {
          if (isset($item['subItems'])) {
              foreach ($item['subItems'] as $subItem) {
                  if (
                      request()->is(ltrim($subItem['path'], '/')) ||
                      request()->is(ltrim($subItem['path'], '/') . '/*')
                  ) {
                      $groupTitle = $group['title'];
                      $itemTitle = $subItem['name'];
                      break 2;
                  }
              }
          } else {
              if (request()->is(ltrim($item['path'], '/')) || request()->is(ltrim($item['path'], '/') . '/*')) {
                  $groupTitle = $group['title'];
                  $itemTitle = $item['name'];
                  break 2;
              }
          }
      }
  }

  if (!$groupTitle) {
      $groupTitle = 'Dashboard';
      $itemTitle = 'Overview';
  }
@endphp

<header
  class="sticky top-0 z-40 flex w-full border-b border-neutral-border bg-neutral-card shadow-[0px_9px_18px_rgba(92,107,121,0.1)] transition-all duration-300 dark:border-gray-800 dark:bg-gray-900"
  x-data="{
      isApplicationMenuOpen: false,
      toggleApplicationMenu() {
          this.isApplicationMenuOpen = !this.isApplicationMenuOpen;
      }
  }">
  <div class="flex w-full items-center justify-between px-6 py-2.5">
    <!-- Left Section (Title and breadcrumbs / Mobile hamburger) -->
    <div class="flex items-center gap-4">
      <!-- Mobile Menu Toggle Button (visible below xl) -->
      <button class="flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 xl:hidden dark:text-gray-400"
        :class="{ 'bg-gray-100 dark:bg-white/[0.03]': $store.sidebar.isMobileOpen }"
        @click.stop="$store.sidebar.toggleMobileOpen()" aria-label="Toggle Mobile Menu">
        <svg x-show="!$store.sidebar.isMobileOpen" width="16" height="12" viewBox="0 0 16 12" fill="none"
          xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd"
            d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
            fill="currentColor"></path>
        </svg>
        <svg x-show="$store.sidebar.isMobileOpen" class="fill-current" width="24" height="24" viewBox="0 0 24 24"
          fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd"
            d="M6.21967 7.28131C5.92678 6.98841 5.92678 6.51354 6.21967 6.22065C6.51256 5.92775 6.98744 5.92775 7.28033 6.22065L11.999 10.9393L16.7176 6.22078C17.0105 5.92789 17.4854 5.92788 17.7782 6.22078C18.0711 6.51367 18.0711 6.98855 17.7782 7.28144L13.0597 12L17.7782 16.7186C18.0711 17.0115 18.0711 17.4863 17.7782 17.7792C17.4854 18.0721 17.0105 18.0721 16.7176 17.7792L11.999 13.0607L7.28033 17.7794C6.98744 18.0722 6.51256 18.0722 6.21967 17.7794C5.92678 17.4865 5.92678 17.0116 6.21967 16.7187L10.9384 12L6.21967 7.28131Z"
            fill="" />
        </svg>
      </button>


      <!-- Breadcrumb Title -->
      <div class="ml-4 hidden items-center gap-3 xl:flex">
        <span class="text-[20px] font-semibold leading-[28px] text-neutral-label dark:text-gray-200">
          {{ $groupTitle }}
        </span>
        <div class="h-[18px] w-px rounded-[2px] bg-neutral-border dark:bg-gray-700"></div>
        <span class="text-[16px] font-medium leading-[24px] text-neutral-label dark:text-gray-400">
          {{ $itemTitle }}
        </span>
      </div>
    </div>

    <!-- Right Section (Actions, Theme Toggle, Profile Dropdown) -->
    <div class="flex items-center gap-4">
      <!-- User Dropdown -->
      <x-header.user-dropdown />
    </div>
  </div>
</header>
