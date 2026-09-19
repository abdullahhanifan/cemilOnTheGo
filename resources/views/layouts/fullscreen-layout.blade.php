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

    <!-- Apply dark mode immediately to prevent flash -->

</head>

<body x-data>

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

    @yield('content')
    {{ $slot ?? '' }}

</body>

@livewireScripts

@stack('scripts')

</html>
