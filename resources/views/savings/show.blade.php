@extends('layouts.app')

@section('title', 'Detail Rekening Simpanan - ' . $savingsAccount->account_number)

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-bank me-2"></i>
    Detail Rekening Simpanan
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    <div class="btn-group me-2">
        <a href="{{ route('savings.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>
            Kembali
        </a>
    </div>
    @can('manage_savings')
    @if($savingsAccount->status === 'active')
    <div class="btn-group me-2">
        <a href="{{ route('savings.deposit', $savingsAccount) }}" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i>
            Setor
        </a>
        <a href="{{ route('savings.withdrawal', $savingsAccount) }}" class="btn btn-warning">
            <i class="bi bi-dash-circle me-1"></i>
            Tarik
        </a>
    </div>
    @endif
    @endcan
    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-three-dots me-1"></i>
            Aksi
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#">
                    <i class="bi bi-printer me-2"></i>Cetak Buku Tabungan
                </a></li>
            <li><a class="dropdown-item" href="#">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Export PDF
                </a></li>
            <li>
                <hr class="dropdown-divider">
            </li>
            <li><a class="dropdown-item text-warning" href="#">
                    <i class="bi bi-lock me-2"></i>Bekukan Rekening
                </a></li>
        </ul>
    </div>
</div>
@endsection

@section('content')
<div class="row">
    <!-- Account Summary -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-body text-center">
                <div class="mb-3">
                    <div class="bg-success rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold"
                        style="width: 80px; height: 80px; font-size: 2rem;">
                        <i class="bi bi-piggy-bank"></i>
                    </div>
                </div>
                <h4 class="card-title">{{ $savingsAccount->account_number }}</h4>
                <p class="text-muted">{{ $savingsAccount->member->full_name }}</p>

                <div class="mb-3">
                    @switch($savingsAccount->status)
                    @case('active')
                    <span class="badge bg-success fs-6">Aktif</span>
                    @break
                    @case('inactive')
                    <span class="badge bg-secondary fs-6">Tidak Aktif</span>
                    @break
                    @case('closed')
                    <span class="badge bg-danger fs-6">Ditutup</span>
                    @break
                    @default
                    <span class="badge bg-light text-dark fs-6">{{ ucfirst($savingsAccount->status) }}</span>
                    @endswitch
                </div>

                <div class="row text-center">
                    <div class="col-12 mb-3">
                        <h3 class="text-success mb-0">
                            Rp {{ number_format($savingsAccount->balance, 0, ',', '.') }}
                        </h3>
                        <small class="text-muted">Saldo Saat Ini</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Info -->
        <div class="card shadow mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Informasi Rekening</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Produk</span>
                        <span class="fw-bold">{{ $savingsAccount->savingsProduct->name ?? '-' }}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Tingkat Bunga</span>
                        <span class="fw-bold text-success">
                            {{ number_format($savingsAccount->savingsProduct->interest_rate ?? 0, 2) }}%
                        </span>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Saldo Minimum</span>
                        <span class="fw-bold">
                            Rp {{ number_format($savingsAccount->savingsProduct->minimum_balance ?? 0, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Tanggal Buka</span>
                    <span class="fw-bold">{{ $savingsAccount->created_at->format('d M Y') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Details -->
    <div class="col-lg-8">
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Total Setoran
                                </div>
                                <div class="h6 mb-0 font-weight-bold text-gray-800">
                                    Rp {{ number_format($statistics['total_deposits'] ?? 0, 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-arrow-up-circle text-success" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Total Penarikan
                                </div>
                                <div class="h6 mb-0 font-weight-bold text-gray-800">
                                    Rp {{ number_format($statistics['total_withdrawals'] ?? 0, 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-arrow-down-circle text-warning" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Jumlah Transaksi
                                </div>
                                <div class="h6 mb-0 font-weight-bold text-gray-800">
                                    {{ number_format($statistics['transaction_count'] ?? 0) }}
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-list-ul text-info" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    Riwayat Transaksi
                </h6>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle"
                        data-bs-toggle="dropdown">
                        Filter
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?type=deposit">Setoran</a></li>
                        <li><a class="dropdown-item" href="?type=withdrawal">Penarikan</a></li>
                        <li><a class="dropdown-item" href="?">Semua</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Jenis</th>
                                <th>Jumlah</th>
                                <th>Saldo</th>
                                <th>Keterangan</th>
                                <th>Petugas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->transaction_date->format('d M Y H:i') }}</td>
                                <td>
                                    @if($transaction->type === 'deposit')
                                    <span class="badge bg-success">Setoran</span>
                                    @else
                                    <span class="badge bg-warning">Penarikan</span>
                                    @endif
                                </td>
                                <td>
                                    <span
                                        class="fw-bold {{ $transaction->type === 'deposit' ? 'text-success' : 'text-warning' }}">
                                        {{ $transaction->type === 'deposit' ? '+' : '-' }}
                                        Rp {{ number_format($transaction->amount, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold">
                                        Rp {{ number_format($transaction->balance_after, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td>{{ $transaction->description ?? '-' }}</td>
                                <td>{{ $transaction->processedBy->name ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-inbox display-4"></i>
                                        <p class="mt-2">Belum ada transaksi</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($transactions->hasPages())
            <div class="card-footer">
                {{ $transactions->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
