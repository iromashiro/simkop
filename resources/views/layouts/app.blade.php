<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="HERMES - Sistem Manajemen Koperasi Muara Enim">
    <meta name="author" content="Mateen - Senior Software Engineer">

    <title>@yield('title', 'Dashboard') - HERMES Koperasi</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    <!-- Additional CSS -->
    @stack('styles')
</head>

<body class="bg-light">
    <div id="app" x-data="{
        sidebarOpen: true,
        darkMode: false,
        notifications: [],
        cooperative: @json(auth()->user()->primaryCooperative() ?? null)
    }" x-init="
        // Initialize theme
        if (localStorage.getItem('darkMode') === 'true') {
            darkMode = true;
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        }

        // Watch for theme changes
        $watch('darkMode', value => {
            localStorage.setItem('darkMode', value);
            document.documentElement.setAttribute('data-bs-theme', value ? 'dark' : 'light');
        });
    ">
        <!-- Top Navigation -->
        @include('layouts.partials.topbar')

        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                @include('layouts.partials.sidebar')

                <!-- Main Content -->
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4" :class="{ 'col-md-12': !sidebarOpen }"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 transform translate-x-4"
                    x-transition:enter-end="opacity-100 transform translate-x-0">

                    <!-- Breadcrumb -->
                    @include('layouts.partials.breadcrumb')

                    <!-- Page Header -->
                    @hasSection('page-header')
                    <div
                        class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        @yield('page-header')
                    </div>
                    @endif

                    <!-- Flash Messages -->
                    @include('layouts.partials.flash-messages')

                    <!-- Page Content -->
                    <div class="content-wrapper">
                        @yield('content')
                    </div>
                </main>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div x-show="$store.loading.isLoading" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
            style="background: rgba(0,0,0,0.5); z-index: 9999;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Memuat...</span>
            </div>
        </div>

        <!-- Toast Container -->
        <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 11">
            @include('layouts.partials.toast')
        </div>
    </div>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>

    <!-- Custom JS -->
    <script src="{{ asset('js/app.js') }}"></script>

    <!-- Additional JS -->
    @stack('scripts')

    <!-- Alpine.js Global Store -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('loading', {
                isLoading: false,
                show() { this.isLoading = true; },
                hide() { this.isLoading = false; }
            });

            Alpine.store('toast', {
                items: [],
                add(message, type = 'info', duration = 5000) {
                    const id = Date.now();
                    this.items.push({ id, message, type, duration });
                    setTimeout(() => this.remove(id), duration);
                },
                remove(id) {
                    this.items = this.items.filter(item => item.id !== id);
                }
            });
        });
    </script>
</body>

</html>
