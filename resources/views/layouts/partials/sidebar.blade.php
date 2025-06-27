<nav class="col-md-3 col-lg-2 d-md-block bg-white sidebar shadow-sm" x-show="sidebarOpen"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 transform -translate-x-full"
    x-transition:enter-end="opacity-100 transform translate-x-0" x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="opacity-100 transform translate-x-0"
    x-transition:leave-end="opacity-0 transform -translate-x-full">

    <div class="position-sticky pt-3">
        <!-- Main Navigation -->
        <ul class="nav flex-column">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                    href="{{ route('dashboard') }}">
                    <i class="bi bi-speedometer2 me-2"></i>
                    Dashboard
                </a>
            </li>

            <!-- Cooperative Management -->
            @can('view_cooperative')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('cooperative.*') ? 'active' : '' }}"
                    href="{{ route('cooperative.show') }}">
                    <i class="bi bi-building me-2"></i>
                    Koperasi
                </a>
            </li>
            @endcan

            <!-- Member Management -->
            @can('view_members')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('members.*') ? 'active' : '' }}"
                    href="{{ route('members.index') }}">
                    <i class="bi bi-people me-2"></i>
                    Anggota
                    @if($pendingMembers = \App\Domain\Member\Models\Member::where('status', 'pending')->count())
                    <span class="badge bg-warning rounded-pill ms-auto">{{ $pendingMembers }}</span>
                    @endif
                </a>
            </li>
            @endcan

            <!-- User Management -->
            @can('view_users')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
                    href="{{ route('users.index') }}">
                    <i class="bi bi-person-gear me-2"></i>
                    Pengguna
                </a>
            </li>
            @endcan
        </ul>

        <!-- Financial Section -->
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Keuangan</span>
        </h6>
        <ul class="nav flex-column">
            <!-- Savings -->
            @can('view_savings')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('savings.*') ? 'active' : '' }}"
                    href="{{ route('savings.index') }}">
                    <i class="bi bi-piggy-bank me-2"></i>
                    Simpanan
                </a>
            </li>
            @endcan

            <!-- Loans -->
            @can('view_loans')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('loans.*') ? 'active' : '' }}"
                    href="{{ route('loans.index') }}">
                    <i class="bi bi-cash-stack me-2"></i>
                    Pinjaman
                    @if($pendingLoans = \App\Domain\Loan\Models\LoanAccount::where('status', 'pending')->count())
                    <span class="badge bg-info rounded-pill ms-auto">{{ $pendingLoans }}</span>
                    @endif
                </a>
            </li>
            @endcan
        </ul>

        <!-- Accounting Section -->
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Akuntansi</span>
        </h6>
        <ul class="nav flex-column">
            <!-- Chart of Accounts -->
            @can('view_accounts')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}"
                    href="{{ route('accounts.index') }}">
                    <i class="bi bi-list-ul me-2"></i>
                    Akun
                </a>
            </li>
            @endcan

            <!-- Journal Entries -->
            @can('view_journal_entries')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('journal-entries.*') ? 'active' : '' }}"
                    href="{{ route('journal-entries.index') }}">
                    <i class="bi bi-journal-text me-2"></i>
                    Jurnal
                    @if($pendingJournals = \App\Domain\Accounting\Models\JournalEntry::where('is_approved',
                    false)->count())
                    <span class="badge bg-warning rounded-pill ms-auto">{{ $pendingJournals }}</span>
                    @endif
                </a>
            </li>
            @endcan

            <!-- Fiscal Periods -->
            @can('manage_fiscal_periods')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('fiscal-periods.*') ? 'active' : '' }}"
                    href="{{ route('fiscal-periods.index') }}">
                    <i class="bi bi-calendar-range me-2"></i>
                    Periode Fiskal
                </a>
            </li>
            @endcan
        </ul>

        <!-- Reports Section -->
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Laporan</span>
        </h6>
        <ul class="nav flex-column">
            @can('view_reports')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                    href="{{ route('reports.index') }}">
                    <i class="bi bi-graph-up me-2"></i>
                    Laporan
                </a>
            </li>
            @endcan
        </ul>

        <!-- Settings Section -->
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Pengaturan</span>
        </h6>
        <ul class="nav flex-column mb-2">
            @can('manage_settings')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}"
                    href="{{ route('settings.index') }}">
                    <i class="bi bi-gear me-2"></i>
                    Pengaturan
                </a>
            </li>
            @endcan
        </ul>
    </div>
</nav>

<style>
    .sidebar {
        position: fixed;
        top: 56px;
        /* Height of topbar */
        bottom: 0;
        left: 0;
        z-index: 100;
        padding: 0;
        box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        overflow-y: auto;
    }

    .sidebar .nav-link {
        color: #333;
        padding: 0.75rem 1rem;
        border-radius: 0;
        transition: all 0.2s ease;
    }

    .sidebar .nav-link:hover {
        background-color: #f8f9fa;
        color: #0d6efd;
    }

    .sidebar .nav-link.active {
        background-color: #0d6efd;
        color: white;
    }

    .sidebar .nav-link.active:hover {
        background-color: #0b5ed7;
    }

    .sidebar-heading {
        font-size: .75rem;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.05em;
    }

    @media (max-width: 767.98px) {
        .sidebar {
            position: fixed;
            top: 56px;
            width: 100%;
            z-index: 1000;
        }
    }
</style>
