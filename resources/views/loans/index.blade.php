@extends('layouts.app')

@section('title', 'Manajemen Pinjaman')

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-cash-stack me-2"></i>
    Manajemen Pinjaman
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    @can('create_loans')
    <div class="btn-group me-2">
        <a href="{{ route('loans.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>
            Pinjaman Baru
        </a>
    </div>
    @endcan
    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i>
            Export
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="{{ route('loans.export', ['format' => 'excel']) }}">
                    <i class="bi bi-file-earmark-excel me-2"></i>Excel
                </a></li>
            <li><a class="dropdown-item" href="{{ route('loans.export', ['format' => 'pdf']) }}">
                    <i class="bi bi-file-earmark-pdf me-2"></i>PDF
                </a></li>
        </ul>
    </div>
</div>
@endsection

@section('content')
<div x-data="loanManagement()" x-init="init()">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Pinjaman
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($statistics['total_loans'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-cash-stack text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Pinjaman Aktif
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($statistics['active_loans'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
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
                                Menunggu Persetujuan
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($statistics['pending_loans'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock text-warning" style="font-size: 2rem;"></i>
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
                                Total Outstanding
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp {{ number_format($statistics['total_outstanding'] ?? 0, 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-exclamation-triangle text-info" style="font-size: 2rem;"></i>
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
            <form method="GET" action="{{ route('loans.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" id="search" name="search"
                            value="{{ request('search') }}" placeholder="Cari nomor pinjaman atau nama anggota...">
                    </div>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Menunggu</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Disetujui
                        </option>
                        <option value="disbursed" {{ request('status') == 'disbursed' ? 'selected' : '' }}>Dicairkan
                        </option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Lunas
                        </option>
                        <option value="defaulted" {{ request('status') == 'defaulted' ? 'selected' : '' }}>Macet
                        </option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="loan_product_id" class="form-label">Produk Pinjaman</label>
                    <select class="form-select" id="loan_product_id" name="loan_product_id">
                        <option value="">Semua Produk</option>
                        <!-- Add loan products options here -->
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

    <!-- Loans Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Pinjaman</h6>
            <div class="d-flex align-items-center">
                <span class="text-muted me-3">
                    Menampilkan {{ $loanAccounts->firstItem() ?? 0 }} - {{ $loanAccounts->lastItem() ?? 0 }}
                    dari {{ $loanAccounts->total() }} pinjaman
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No. Pinjaman</th>
                            <th>Anggota</th>
                            <th>Jumlah Pinjaman</th>
                            <th>Sisa Pinjaman</th>
                            <th>Status</th>
                            <th>Tanggal Pengajuan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loanAccounts as $loan)
                        <tr>
                            <td>
                                <span class="fw-bold text-primary">{{ $loan->account_number }}</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-3">
                                        <div class="bg-info rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                            style="width: 40px; height: 40px;">
                                            {{ strtoupper(substr($loan->member->full_name, 0, 2)) }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fw-bold">{{ $loan->member->full_name }}</div>
                                        <small class="text-muted">{{ $loan->member->member_number }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold">
                                    Rp {{ number_format($loan->principal_amount, 0, ',', '.') }}
                                </div>
                                <small class="text-muted">
                                    {{ $loan->term_months }} bulan @ {{ number_format($loan->interest_rate, 2) }}%
                                </small>
                            </td>
                            <td>
                                <div class="fw-bold text-warning">
                                    Rp {{ number_format($loan->outstanding_balance, 0, ',', '.') }}
                                </div>
                            </td>
                            <td>
                                @switch($loan->status)
                                @case('pending')
                                <span class="badge bg-warning">Menunggu</span>
                                @break
                                @case('approved')
                                <span class="badge bg-info">Disetujui</span>
                                @break
                                @case('disbursed')
                                <span class="badge bg-primary">Dicairkan</span>
                                @break
                                @case('active')
                                <span class="badge bg-success">Aktif</span>
                                @break
                                @case('completed')
                                <span class="badge bg-secondary">Lunas</span>
                                @break
                                @case('defaulted')
                                <span class="badge bg-danger">Macet</span>
                                @break
                                @default
                                <span class="badge bg-light text-dark">{{ ucfirst($loan->status) }}</span>
                                @endswitch
                            </td>
                            <td>{{ $loan->created_at->format('d M Y') }}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    @can('view_loans')
                                    <a href="{{ route('loans.show', $loan) }}" class="btn btn-sm btn-outline-primary"
                                        title="Lihat Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @endcan

                                    @can('manage_loans')
                                    @if($loan->status === 'pending')
                                    <button type="button" class="btn btn-sm btn-outline-success" title="Setujui"
                                        @click="approveLoan({{ $loan->id }})">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                    @endif

                                    @if($loan->status === 'approved')
                                    <button type="button" class="btn btn-sm btn-outline-info" title="Cairkan"
                                        @click="disburseLoan({{ $loan->id }})">
                                        <i class="bi bi-cash"></i>
                                    </button>
                                    @endif

                                    @if(in_array($loan->status, ['active', 'disbursed']))
                                    <a href="{{ route('loans.payment', $loan) }}" class="btn btn-sm btn-outline-warning"
                                        title="Bayar">
                                        <i class="bi bi-credit-card"></i>
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
                                    <p class="mt-2">Tidak ada pinjaman ditemukan</p>
                                    @can('create_loans')
                                    <a href="{{ route('loans.create') }}" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-1"></i>
                                        Buat Pinjaman Pertama
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

        @if($loanAccounts->hasPages())
        <div class="card-footer">
            {{ $loanAccounts->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    function loanManagement() {
    return {
        loading: false,

        init() {
            // Initialize any required functionality
        },

        async approveLoan(loanId) {
            if (!confirm('Apakah Anda yakin ingin menyetujui pinjaman ini?')) {
                return;
            }

            this.loading = true;

            try {
                const response = await fetch(`/loans/${loanId}/approve`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    HERMES.utils.showToast('Pinjaman berhasil disetujui', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    const error = await response.json();
                    HERMES.utils.showToast(error.message || 'Gagal menyetujui pinjaman', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                HERMES.utils.showToast('Terjadi kesalahan sistem', 'error');
            } finally {
                this.loading = false;
            }
        },

        async disburseLoan(loanId) {
            if (!confirm('Apakah Anda yakin ingin mencairkan pinjaman ini?')) {
                return;
            }

            this.loading = true;

            try {
                const response = await fetch(`/loans/${loanId}/disburse`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    HERMES.utils.showToast('Pinjaman berhasil dicairkan', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    const error = await response.json();
                    HERMES.utils.showToast(error.message || 'Gagal mencairkan pinjaman', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                HERMES.utils.showToast('Terjadi kesalahan sistem', 'error');
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endpush
@endsection
