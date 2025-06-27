@extends('layouts.app')

@section('title', 'Manajemen Simpanan')

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-piggy-bank me-2"></i>
    Manajemen Simpanan
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    @can('create_savings')
    <div class="btn-group me-2">
        <a href="{{ route('savings.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>
            Buka Rekening Baru
        </a>
    </div>
    @endcan
    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i>
            Export
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="{{ route('savings.export', ['format' => 'excel']) }}">
                    <i class="bi bi-file-earmark-excel me-2"></i>Excel
                </a></li>
            <li><a class="dropdown-item" href="{{ route('savings.export', ['format' => 'pdf']) }}">
                    <i class="bi bi-file-earmark-pdf me-2"></i>PDF
                </a></li>
        </ul>
    </div>
</div>
@endsection

@section('content')
<div x-data="savingsManagement()" x-init="init()">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Rekening
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($statistics['total_accounts'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-bank text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Saldo
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp {{ number_format($statistics['total_balance'] ?? 0, 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-piggy-bank text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Setoran Bulan Ini
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp {{ number_format($statistics['monthly_deposits'] ?? 0, 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-arrow-up-circle text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Penarikan Bulan Ini
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp {{ number_format($statistics['monthly_withdrawals'] ?? 0, 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-arrow-down-circle text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter & Pencarian</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('savings.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" id="search" name="search"
                            value="{{ request('search') }}" placeholder="Cari nomor rekening atau nama anggota...">
                    </div>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Tidak Aktif
                        </option>
                        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Ditutup</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="savings_product_id" class="form-label">Produk Simpanan</label>
                    <select class="form-select" id="savings_product_id" name="savings_product_id">
                        <option value="">Semua Produk</option>
                        <!-- Add savings products options here -->
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i>
                            Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Savings Accounts Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Rekening Simpanan</h6>
            <div class="d-flex align-items-center">
                <span class="text-muted me-3">
                    Menampilkan {{ $savingsAccounts->firstItem() ?? 0 }} - {{ $savingsAccounts->lastItem() ?? 0 }}
                    dari {{ $savingsAccounts->total() }} rekening
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No. Rekening</th>
                            <th>Anggota</th>
                            <th>Produk Simpanan</th>
                            <th>Saldo</th>
                            <th>Status</th>
                            <th>Tanggal Buka</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($savingsAccounts as $account)
                        <tr>
                            <td>
                                <span class="fw-bold text-primary">{{ $account->account_number }}</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-3">
                                        <div class="bg-success rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                            style="width: 40px; height: 40px;">
                                            {{ strtoupper(substr($account->member->full_name, 0, 2)) }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fw-bold">{{ $account->member->full_name }}</div>
                                        <small class="text-muted">{{ $account->member->member_number }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold">{{ $account->savingsProduct->name ?? '-' }}</div>
                                <small class="text-muted">
                                    Bunga: {{ number_format($account->savingsProduct->interest_rate ?? 0, 2) }}%
                                </small>
                            </td>
                            <td>
                                <div class="fw-bold text-success">
                                    Rp {{ number_format($account->balance, 0, ',', '.') }}
                                </div>
                            </td>
                            <td>
                                @switch($account->status)
                                @case('active')
                                <span class="badge bg-success">Aktif</span>
                                @break
                                @case('inactive')
                                <span class="badge bg-secondary">Tidak Aktif</span>
                                @break
                                @case('closed')
                                <span class="badge bg-danger">Ditutup</span>
                                @break
                                @default
                                <span class="badge bg-light text-dark">{{ ucfirst($account->status) }}</span>
                                @endswitch
                            </td>
                            <td>{{ $account->created_at->format('d M Y') }}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    @can('view_savings')
                                    <a href="{{ route('savings.show', $account) }}"
                                        class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @endcan

                                    @can('manage_savings')
                                    @if($account->status === 'active')
                                    <a href="{{ route('savings.deposit', $account) }}"
                                        class="btn btn-sm btn-outline-success" title="Setor">
                                        <i class="bi bi-plus-circle"></i>
                                    </a>
                                    <a href="{{ route('savings.withdrawal', $account) }}"
                                        class="btn btn-sm btn-outline-warning" title="Tarik">
                                        <i class="bi bi-dash-circle"></i>
                                    </a>
                                    @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-inbox display-4"></i>
                                    <p class="mt-2">Tidak ada rekening simpanan ditemukan</p>
                                    @can('create_savings')
                                    <a href="{{ route('savings.create') }}" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-1"></i>
                                        Buka Rekening Pertama
                                    </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($savingsAccounts->hasPages())
        <div class="card-footer">
            {{ $savingsAccounts->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    function savingsManagement() {
    return {
        loading: false,

        init() {
            // Initialize any required functionality
        }
    }
}
</script>
@endpush
@endsection
