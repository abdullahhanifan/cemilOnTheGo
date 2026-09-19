<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-neutral-bg dark:bg-gray-900">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>{{ $title ?? 'Dashboard' }} | {{ config('app.name') }}</title>

  <!-- Livewire -->
  @livewireStyles

  <!-- Scripts -->
  @vite(['resources/css/app.css', 'resources/js/app.js'])

  <!-- Theme Store -->
  <style>
    [x-cloak] {
      display: none !important;
    }
  </style>
  <!-- Theme Store (Locked to Light Mode) -->
  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.store('theme', {
        init() {
          this.theme = 'light';
          this.updateTheme();
        },
        theme: 'light',
        resolvedTheme: 'light',
        set(value) {
          this.theme = 'light';
          this.updateTheme();
        },
        toggle() {
          // Disabled
        },
        updateTheme() {
          const html = document.documentElement;
          html.classList.remove('dark');
          this.resolvedTheme = 'light';
          html.dataset['theme'] = 'light';
          html.style.colorScheme = 'light';
          if (document.body) {
            document.body.dataset['theme'] = 'light';
            document.body.style.colorScheme = 'light';
          }
        }
      });

      Alpine.store('sidebar', {
        isExpanded: false,
        isMobileOpen: false,
        isHovered: false,

        init() {
          const savedState = localStorage.getItem('sidebarExpanded');
          if (window.innerWidth >= 1280) {
            this.isExpanded = savedState === null ? true : savedState === 'true';
          } else {
            this.isExpanded = false;
          }
          this.isMobileOpen = false;

          window.addEventListener('resize', () => {
            this.handleResize();
          });
        },

        handleResize() {
          if (window.innerWidth < 1280) {
            if (this.isMobileOpen) {
              this.isMobileOpen = false;
            }
          } else {
            this.isMobileOpen = false;
            const savedState = localStorage.getItem('sidebarExpanded');
            this.isExpanded = savedState === null ? true : savedState === 'true';
          }
        },

        toggleExpanded() {
          this.isExpanded = !this.isExpanded;
          this.isMobileOpen = false;

          if (window.innerWidth >= 1280) {
            localStorage.setItem('sidebarExpanded', this.isExpanded);
          }
        },

        toggleMobileOpen() {
          this.isMobileOpen = !this.isMobileOpen;
        },

        setMobileOpen(val) {
          this.isMobileOpen = val;
        },

        setHovered(val) {
          if (window.innerWidth >= 1280 && !this.isExpanded) {
            this.isHovered = val;
          }
        }
      });
    });
  </script>

  <!-- Force light mode immediately -->
  <script>
    (function() {
      document.documentElement.classList.remove('dark');
      document.documentElement.dataset['theme'] = 'light';
      document.documentElement.style.colorScheme = 'light';
    })();
  </script>

  <!-- Timezone Detector -->
  <script>
    (function() {
      const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
      const currentCookie = document.cookie.split('; ').find(row => row.startsWith('timezone='));
      const cookieValue = currentCookie ? decodeURIComponent(currentCookie.split('=')[1]) : null;
      if (timezone && cookieValue !== timezone) {
        document.cookie = "timezone=" + encodeURIComponent(timezone) + "; path=/; max-age=31536000; SameSite=Lax";
        if (!cookieValue) {
          window.location.reload();
        }
      }
    })();
  </script>
</head>

<body class="overflow-x-hidden">



  <div class="sidebar-expanded min-h-screen xl:flex" x-data
    :class="{ 'sidebar-expanded': $store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen }">
    @persist('backdrop')
      @include('layouts.backdrop')
    @endpersist
    @persist('sidebar')
      @include('layouts.sidebar')
    @endpersist

    <div
      class="ml-0 min-w-0 flex-1 transition-all duration-300 ease-in-out xl:ml-[90px] [.sidebar-expanded_&]:xl:ml-[300px]">
      <!-- app header start -->
      @include('layouts.app-header')
      <!-- app header end -->
      <div class="mx-auto p-4 md:p-6">
        @yield('content')
        {{ $slot ?? '' }}
      </div>
    </div>

  </div>

  <!-- Global Livewire Toast Notifications Component -->
  <livewire:ui.global-notifications />

  @if (session()->has('success'))
    <div x-data x-init="$nextTick(() => $dispatch('notify', { type: 'success', message: '{{ addslashes(session('success')) }}' }))"></div>
  @endif
  @if (session()->has('error'))
    <div x-data x-init="$nextTick(() => $dispatch('notify', { type: 'error', message: '{{ addslashes(session('error')) }}' }))"></div>
  @endif
  <!-- Global Livewire Loading Modal -->
  <div x-data="{ isLoading: false }" @loading-start.window="isLoading = true" @loading-stop.window="isLoading = false"
    x-show="isLoading" x-cloak class="fixed inset-0 z-[2147483647] flex items-center justify-center p-5">

    <!-- Backdrop -->
    <div class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]" x-show="isLoading"
      x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
      x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    </div>

    <!-- Modal Content -->
    <div
      class="relative flex flex-col items-center justify-center rounded-3xl border border-gray-100 bg-white p-8 text-center shadow-xl dark:border-gray-800 dark:bg-gray-900"
      style="width: 250px;" x-show="isLoading" x-transition:enter="transition ease-out duration-300"
      x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100"
      x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 transform scale-100"
      x-transition:leave-end="opacity-0 transform scale-95">
      <x-ui.spinner.spinner-four :icon-only="true" class="h-16 w-16 text-accent-primary" />
      <p class="mt-5 text-sm font-semibold text-gray-700 dark:text-gray-300">Loading...</p>
    </div>
  </div>

  <script>
    // document.addEventListener('livewire:init', () => {
    //   let activeRequests = 0;

    //   Livewire.hook('request', ({
    //     succeed,
    //     fail
    //   }) => {
    //     activeRequests++;
    //     if (activeRequests === 1) {
    //       window.dispatchEvent(new CustomEvent('loading-start'));
    //     }

    //     const handleResponse = () => {
    //       activeRequests--;
    //       if (activeRequests <= 0) {
    //         activeRequests = 0;
    //         window.dispatchEvent(new CustomEvent('loading-stop'));
    //       }
    //     };

    //     succeed(handleResponse);
    //     fail(handleResponse);
    //   });
    // });
  </script>

</body>

@livewireScripts

@stack('scripts')

</html>
