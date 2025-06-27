@extends('layouts.app')

@section('title', 'Bagan Akun')

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-list-ul me-2"></i>
    Bagan Akun
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    @can('create_accounts')
    <div class="btn-group me-2">
        <a href="{{ route('accounts.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>
            Tambah Akun
        </a>
    </div>
    @endcan
    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i>
            Export
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="{{ route('accounts.export', ['format' => 'excel']) }}">
                    <i class="bi bi-file-earmark-excel me-2"></i>Excel
                </a></li>
            <li><a class="dropdown-item" href="{{ route('accounts.export', ['format' => 'pdf']) }}">
                    <i class="bi bi-file-earmark-pdf me-2"></i>PDF
                </a></li>
        </ul>
    </div>
</div>
@endsection

@section('content')
<div x-data="accountManagement()" x-init="init()">
    <!-- Account Type Summary -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Aset
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($statistics['asset_accounts'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-building text-primary" style="font-size: 2rem;"></i>
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
                                Kewajiban
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($statistics['liability_accounts'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-credit-card text-warning" style="font-size: 2rem;"></i>
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
                                Modal
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($statistics['equity_accounts'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-pie-chart text-success" style="font-size: 2rem;"></i>
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
                                Pendapatan & Beban
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($statistics['revenue_expense_accounts'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-graph-up text-info" style="font-size: 2rem;"></i>
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
            <form method="GET" action="{{ route('accounts.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" id="search" name="search"
                            value="{{ request('search') }}" placeholder="Cari kode atau nama akun...">
                    </div>
                </div>

                <div class="col-md-3">
                    <label for="type" class="form-label">Jenis Akun</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Semua Jenis</option>
                        <option value="asset" {{ request('type') == 'asset' ? 'selected' : '' }}>Aset</option>
                        <option value="liability" {{ request('type') == 'liability' ? 'selected' : '' }}>Kewajiban
                        </option>
                        <option value="equity" {{ request('type') == 'equity' ? 'selected' : '' }}>Modal</option>
                        <option value="revenue" {{ request('type') == 'revenue' ? 'selected' : '' }}>Pendapatan</option>
                        <option value="expense" {{ request('type') == 'expense' ? 'selected' : '' }}>Beban</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Tidak Aktif
                        </option>
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

    <!-- Accounts Tree View -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Struktur Bagan Akun</h6>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="expandAll">
                    <i class="bi bi-arrows-expand"></i> Buka Semua
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="collapseAll">
                    <i class="bi bi-arrows-collapse"></i> Tutup Semua
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">Kode & Nama Akun</th>
                            <th>Jenis</th>
                            <th>Saldo</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accountsTree as $account)
                        <tr class="account-row" data-level="{{ $account['level'] }}"
                            data-parent="{{ $account['parent_id'] ?? '' }}">
                            <td>
                                <div class="d-flex align-items-center"
                                    style="padding-left: {{ $account['level'] * 20 }}px;">
                                    @if($account['has_children'])
                                    <button type="button" class="btn btn-sm btn-link p-0 me-2 toggle-children"
                                        data-account-id="{{ $account['id'] }}">
                                        <i class="bi bi-chevron-right"></i>
                                    </button>
                                    @else
                                    <span class="me-4"></span>
                                    @endif

                                    <div>
                                        <div class="fw-bold">
                                            {{ $account['code'] }} - {{ $account['name'] }}
                                        </div>
                                        @if($account['description'])
                                        <small class="text-muted">{{ $account['description'] }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @switch($account['type'])
                                @case('asset')
                                <span class="badge bg-primary">Aset</span>
                                @break
                                @case('liability')
                                <span class="badge bg-warning">Kewajiban</span>
                                @break
                                @case('equity')
                                <span class="badge bg-success">Modal</span>
                                @break
                                @case('revenue')
                                <span class="badge bg-info">Pendapatan</span>
                                @break
                                @case('expense')
                                <span class="badge bg-danger">Beban</span>
                                @break
                                @default
                                <span class="badge bg-secondary">{{ ucfirst($account['type']) }}</span>
                                @endswitch
                            </td>
                            <td>
                                <span class="fw-bold {{ $account['balance'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    Rp {{ number_format(abs($account['balance']), 0, ',', '.') }}
                                    @if($account['balance'] < 0) (Kredit) @endif </span>
                            </td>
                            <td>
                                @if($account['is_active'])
                                <span class="badge bg-success">Aktif</span>
                                @else
                                <span class="badge bg-secondary">Tidak Aktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    @can('view_accounts')
                                    <a href="{{ route('accounts.show', $account['id']) }}"
                                        class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @endcan

                                    @can('edit_accounts')
                                    <a href="{{ route('accounts.edit', $account['id']) }}"
                                        class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @endcan

                                    @can('delete_accounts')
                                    @if($account['balance'] == 0 && !$account['has_children'])
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus"
                                        @click="deleteAccount({{ $account['id'] }}, '{{ $account['name'] }}')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-inbox display-4"></i>
                                    <p class="mt-2">Belum ada akun yang dibuat</p>
                                    @can('create_accounts')
                                    <a href="{{ route('accounts.create') }}" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-1"></i>
                                        Buat Akun Pertama
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
    </div>
</div>

@push('scripts')
<script>
    function accountManagement() {
    return {
        loading: false,

        init() {
            this.initializeTreeView();
        },

        initializeTreeView() {
            // Initialize tree view functionality
            document.querySelectorAll('.toggle-children').forEach(button => {
                button.addEventListener('click', (e) => {
                    const accountId = e.target.closest('button').dataset.accountId;
                    this.toggleChildren(accountId);
                });
            });
        },

        toggleChildren(accountId) {
            const button = document.querySelector(`[data-account-id="${accountId}"]`);
            const icon = button.querySelector('i');
            const isExpanded = icon.classList.contains('bi-chevron-down');

            // Toggle icon
            if (isExpanded) {
                icon.classList.remove('bi-chevron-down');
                icon.classList.add('bi-chevron-right');
                this.hideChildren(accountId);
            } else {
                icon.classList.remove('bi-chevron-right');
                icon.classList.add('bi-chevron-down');
                this.showChildren(accountId);
            }
        },

        showChildren(parentId) {
            document.querySelectorAll(`[data-parent="${parentId}"]`).forEach(row => {
                row.style.display = 'table-row';
            });
        },

        hideChildren(parentId) {
            document.querySelectorAll(`[data-parent="${parentId}"]`).forEach(row => {
                row.style.display = 'none';
                // Also hide nested children
                const accountId = row.querySelector('[data-account-id]')?.dataset.accountId;
                if (accountId) {
                    this.hideChildren(accountId);
                }
            });
        },

        expandAll() {
            document.querySelectorAll('.account-row').forEach(row => {
                row.style.display = 'table-row';
            });
            document.querySelectorAll('.toggle-children i').forEach(icon => {
                icon.classList.remove('bi-chevron-right');
                icon.classList.add('bi-chevron-down');
            });
        },

        collapseAll() {
            document.querySelectorAll('.account-row[data-level="1"], .account-row[data-level="2"], .account-row[data-level="3"]').forEach(row => {
                if (row.dataset.level !== '0') {
                    row.style.display = 'none';
                }
            });
            document.querySelectorAll('.toggle-children i').forEach(icon => {
                icon.classList.remove('bi-chevron-down');
                icon.classList.add('bi-chevron-right');
            });
        },

        async deleteAccount(accountId, accountName) {
            if (!confirm(`Apakah Anda yakin ingin menghapus akun "${accountName}"?\n\nTindakan ini tidak dapat dibatalkan.`)) {
                return;
            }

            this.loading = true;

            try {
                const response = await fetch(`/accounts/${accountId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    HERMES.utils.showToast('Akun berhasil dihapus', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    const error = await response.json();
                    HERMES.utils.showToast(error.message || 'Gagal menghapus akun', 'error');
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

@push('styles')
<style>
    .account-row[data-level="1"] {
        background-color: rgba(13, 110, 253, 0.05);
    }

    .account-row[data-level="2"] {
        background-color: rgba(13, 110, 253, 0.03);
    }

    .account-row[data-level="3"] {
        background-color: rgba(13, 110, 253, 0.01);
    }

    .toggle-children {
        border: none !important;
        color: #6c757d;
        transition: transform 0.2s ease;
    }

    .toggle-children:hover {
        color: #0d6efd;
        transform: scale(1.1);
    }

    .border-left-primary {
        border-left: 0.25rem solid #4e73df !important;
    }

    .border-left-success {
        border-left: 0.25rem solid #1cc88a !important;
    }

    .border-left-info {
        border-left: 0.25rem solid #36b9cc !important;
    }

    .border-left-warning {
        border-left: 0.25rem solid #f6c23e !important;
    }
</style>
@endpush
@endsection
