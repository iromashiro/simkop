{{-- resources/views/layouts/sidebar.blade.php --}}
<div class="sidebar-brand">
    <h4>SIMKOP</h4>
    <small>Sistem Informasi Manajemen Koperasi</small>
</div>

<nav class="sidebar-nav">
    <!-- Dashboard -->
    <div class="nav-item">
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            Dashboard
        </a>
    </div>

    @if(auth()->user()->hasRole('admin_dinas'))
    <!-- Admin Dinas Menu -->
    <div class="nav-item">
        <div class="nav-link text-white-50 small text-uppercase fw-bold mt-3 mb-2">
            <i class="bi bi-gear"></i>
            Administrasi
        </div>
    </div>

    <div class="nav-item">
        <a href="{{ route('admin.cooperatives.index') }}"
            class="nav-link {{ request()->routeIs('admin.cooperatives.*') ? 'active' : '' }}">
            <i class="bi bi-building"></i>
            Kelola Koperasi
        </a>
    </div>

    <div class="nav-item">
        <a href="{{ route('admin.users.index') }}"
            class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i>
            Kelola Pengguna
        </a>
    </div>

    <div class="nav-item">
        <a href="{{ route('admin.reports.approval') }}"
            class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
            <i class="bi bi-clipboard-check"></i>
            Persetujuan Laporan
            @if($pendingReports ?? 0 > 0)
            <span class="notification-badge">{{ $pendingReports }}</span>
            @endif
        </a>
    </div>
    @endif

    @if(auth()->user()->hasAnyRole(['admin_koperasi', 'staff_koperasi']))
    <!-- Financial Reports Menu -->
    <div class="nav-item">
        <div class="nav-link text-white-50 small text-uppercase fw-bold mt-3 mb-2">
            <i class="bi bi-graph-up"></i>
            Laporan Keuangan
        </div>
    </div>

    <div class="nav-item">
        <a href="{{ route('financial.balance-sheet.index') }}"
            class="nav-link {{ request()->routeIs('financial.balance-sheet.*') ? 'active' : '' }}">
            <i class="bi bi-clipboard-data"></i>
            Laporan Posisi Keuangan
        </a>
    </div>

    <div class="nav-item">
        <a href="{{ route('financial.income-statement.index') }}"
            class="nav-link {{ request()->routeIs('financial.income-statement.*') ? 'active' : '' }}">
            <i class="bi bi-bar-chart"></i>
            Laporan Laba Rugi
        </a>
    </div>

    <div class="nav-item">
        <a href="{{ route('financial.equity-changes.index') }}"
            class="nav-link {{ request()->routeIs('financial.equity-changes.*') ? 'active' : '' }}">
            <i class="bi bi-arrow-up-right"></i>
            Laporan Perubahan Ekuitas
        </a>
    </div>

    <div class="nav-item">
        <a href="{{ route('financial.cash-flow.index') }}"
            class="nav-link {{ request()->routeIs('financial.cash-flow.*') ? 'active' : '' }}">
            <i class="bi bi-cash-stack"></i>
            Laporan Arus Kas
        </a>
    </div>

    <div class="nav-item">
        <a href="{{ route('financial.member-savings.index') }}"
            class="nav-link {{ request()->routeIs('financial.member-savings.*') ? 'active' : '' }}">
            <i class="bi bi-piggy-bank"></i>
            Simpanan Anggota
        </a>
    </div>

    <div class="nav-item">
        <a href="{{ route('financial.member-receivables.index') }}"
            class="nav-link {{ request()->routeIs('financial.member-receivables.*') ? 'active' : '' }}">
            <i class="bi bi-credit-card"></i>
            Piutang Anggota
        </a>
    </div>

    <div class="nav-item">
        <a href="{{ route('financial.npl-receivables.index') }}"
            class="nav-link {{ request()->routeIs('financial.npl-receivables.*') ? 'active' : '' }}">
            <i class="bi bi-exclamation-triangle"></i>
            Piutang Bermasalah
        </a>
    </div>

    <div class="nav-item">
        <a href="{{ route('financial.shu-distribution.index') }}"
            class="nav-link {{ request()->routeIs('financial.shu-distribution.*') ? 'active' : '' }}">
            <i class="bi bi-share"></i>
            Pembagian SHU
        </a>
    </div>

    <div class="nav-item">
        <a href="{{ route('financial.budget-plan.index') }}"
            class="nav-link {{ request()->routeIs('financial.budget-plan.*') ? 'active' : '' }}">
            <i class="bi bi-calendar-check"></i>
            Rencana Anggaran
        </a>
    </div>

    <div class="nav-item">
        <a href="{{ route('financial.notes.index') }}"
            class="nav-link {{ request()->routeIs('financial.notes.*') ? 'active' : '' }}">
            <i class="bi bi-journal-text"></i>
            Catatan Laporan Keuangan
        </a>
    </div>
    @endif

    <!-- Reports & Export -->
    <div class="nav-item">
        <div class="nav-link text-white-50 small text-uppercase fw-bold mt-3 mb-2">
            <i class="bi bi-download"></i>
            Ekspor & Laporan
        </div>
    </div>

    <div class="nav-item">
        <a href="{{ route('reports.export.index') }}"
            class="nav-link {{ request()->routeIs('reports.export.*') ? 'active' : '' }}">
            <i class="bi bi-file-earmark-pdf"></i>
            Ekspor Laporan
        </a>
    </div>

    <div class="nav-item">
        <a href="{{ route('reports.batch.index') }}"
            class="nav-link {{ request()->routeIs('reports.batch.*') ? 'active' : '' }}">
            <i class="bi bi-files"></i>
            Ekspor Batch
        </a>
    </div>

    <!-- Notifications -->
    <div class="nav-item">
        <a href="{{ route('notifications.index') }}"
            class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
            <i class="bi bi-bell"></i>
            Notifikasi
            @if($unreadNotifications ?? 0 > 0)
            <span class="notification-badge">{{ $unreadNotifications }}</span>
            @endif
        </a>
    </div>
</nav>
