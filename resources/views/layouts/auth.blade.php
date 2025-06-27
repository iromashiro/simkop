<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="HERMES - Sistem Manajemen Koperasi Muara Enim">

    <title>@yield('title', 'Masuk') - HERMES Koperasi</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">

    @stack('styles')
</head>

<body class="bg-primary-subtle">
    <div class="container-fluid vh-100">
        <div class="row h-100">
            <!-- Left Side - Branding -->
            <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center bg-primary text-white">
                <div class="text-center">
                    <div class="mb-4">
                        <i class="bi bi-building-gear display-1"></i>
                    </div>
                    <h1 class="display-4 fw-bold mb-3">HERMES</h1>
                    <p class="lead mb-4">Sistem Manajemen Koperasi</p>
                    <p class="fs-5 opacity-75">Muara Enim Regency</p>
                    <div class="mt-5">
                        <div class="row text-center">
                            <div class="col-4">
                                <i class="bi bi-people-fill fs-1 mb-2"></i>
                                <p class="small">Manajemen Anggota</p>
                            </div>
                            <div class="col-4">
                                <i class="bi bi-cash-stack fs-1 mb-2"></i>
                                <p class="small">Simpan Pinjam</p>
                            </div>
                            <div class="col-4">
                                <i class="bi bi-graph-up fs-1 mb-2"></i>
                                <p class="small">Laporan Keuangan</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Authentication Form -->
            <div class="col-lg-6 d-flex align-items-center justify-content-center">
                <div class="w-100" style="max-width: 400px;">
                    <!-- Mobile Logo -->
                    <div class="text-center mb-4 d-lg-none">
                        <i class="bi bi-building-gear text-primary" style="font-size: 3rem;"></i>
                        <h2 class="text-primary fw-bold">HERMES</h2>
                        <p class="text-muted">Sistem Manajemen Koperasi</p>
                    </div>

                    <!-- Flash Messages -->
                    @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    @endif

                    @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    @endif

                    <!-- Authentication Form -->
                    @yield('content')

                    <!-- Footer -->
                    <div class="text-center mt-4">
                        <p class="text-muted small">
                            &copy; {{ date('Y') }} HERMES Koperasi.
                            <br>Dikembangkan oleh <strong>Mateen</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    @stack('scripts')
</body>

</html>
