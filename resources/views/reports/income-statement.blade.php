@extends('layouts.app')

@section('title', 'Laporan Laba Rugi')

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-graph-up me-2"></i>
    Laporan Laba Rugi
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    <div class="btn-group me-2">
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>
            Kembali
        </a>
    </div>
    <div class="btn-group me-2">
        <button type="button" class="btn btn-outline-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>
            Cetak
        </button>
    </div>
    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i>
            Export
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item"
                    href="{{ route('reports.income-statement.export', ['format' => 'pdf'] + request()->all()) }}">
                    <i class="bi bi-file-earmark-pdf me-2"></i>PDF
                </a></li>
            <li><a class="dropdown-item"
                    href="{{ route('reports.income-statement.export', ['format' => 'excel'] + request()->all()) }}">
                    <i class="bi bi-file-earmark-excel me-2"></i>Excel
                </a></li>
        </ul>
    </div>
</div>
@endsection

@section('content')
<div x-data="incomeStatementReport()" x-init="init()">
    <!-- Report Parameters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.income-statement') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="start_date" name="start_date"
                        value="{{ request('start_date', now()->startOfYear()->format('Y-m-d')) }}">
                </div>

                <div class="col-md-3">
                    <label for="end_date" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="end_date" name="end_date"
                        value="{{ request('end_date', now()->format('Y-m-d')) }}">
                </div>

                <div class="col-md-3">
                    <label for="fiscal_period_id" class="form-label">Periode Fiskal</label>
                    <select class="form-select" id="fiscal_period_id" name="fiscal_period_id">
                        <option value="">Semua Periode</option>
                        @foreach($fiscalPeriods as $period)
                        <option value="{{ $period->id }}"
                            {{ request('fiscal_period_id') == $period->id ? 'selected' : '' }}>
                            {{ $period->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-refresh me-1"></i>
                        Update Laporan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Income Statement Report -->
    <div class="card shadow" id="income-statement-report">
        <div class="card-body">
            <!-- Report Header -->
            <div class="text-center mb-4">
                <h3 class="fw-bold">{{ auth()->user()->primaryCooperative()->name ?? 'HERMES Koperasi' }}</h3>
                <h4>LAPORAN LABA RUGI</h4>
                <h5>
                    Periode {{ \Carbon\Carbon::parse(request('start_date', now()->startOfYear()))->format('d F Y') }}
                    s/d {{ \Carbon\Carbon::parse(request('end_date', now()))->format('d F Y') }}
                </h5>
            </div>

            <!-- Revenue Section -->
            <div class="mb-4">
                <h5 class="fw-bold text-success border-bottom pb-2 mb-3">PENDAPATAN</h5>

                @if(isset($incomeStatement['revenue']) && count($incomeStatement['revenue']) > 0)
                @foreach($incomeStatement['revenue'] as $revenue)
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div style="padding-left: 20px;">
                        <span class="fw-bold">{{ $revenue['code'] }}</span>
                        <span>{{ $revenue['name'] }}</span>
                    </div>
                    <span class="fw-bold">
                        Rp {{ number_format($revenue['balance'], 0, ',', '.') }}
                    </span>
                </div>
                @endforeach

                <div class="d-flex justify-content-between align-items-center border-top pt-2 mb-4">
                    <h6 class="fw-bold">TOTAL PENDAPATAN</h6>
                    <h6 class="fw-bold text-success">
                        Rp {{ number_format($incomeStatement['totals']['total_revenue'] ?? 0, 0, ',', '.') }}
                    </h6>
                </div>
                @else
                <div class="text-center text-muted py-3">
                    <p>Tidak ada data pendapatan untuk periode ini</p>
                </div>
                @endif
            </div>

            <!-- Expenses Section -->
            <div class="mb-4">
                <h5 class="fw-bold text-danger border-bottom pb-2 mb-3">BEBAN</h5>

                @if(isset($incomeStatement['expenses']) && count($incomeStatement['expenses']) > 0)
                @foreach($incomeStatement['expenses'] as $expense)
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div style="padding-left: 20px;">
                        <span class="fw-bold">{{ $expense['code'] }}</span>
                        <span>{{ $expense['name'] }}</span>
                    </div>
                    <span class="fw-bold">
                        Rp {{ number_format($expense['balance'], 0, ',', '.') }}
                    </span>
                </div>
                @endforeach

                <div class="d-flex justify-content-between align-items-center border-top pt-2 mb-4">
                    <h6 class="fw-bold">TOTAL BEBAN</h6>
                    <h6 class="fw-bold text-danger">
                        Rp {{ number_format($incomeStatement['totals']['total_expenses'] ?? 0, 0, ',', '.') }}
                    </h6>
                </div>
                @else
                <div class="text-center text-muted py-3">
                    <p>Tidak ada data beban untuk periode ini</p>
                </div>
                @endif
            </div>

            <!-- Net Income Section -->
            <div class="border-top border-bottom py-3 mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold">LABA (RUGI) BERSIH</h5>
                    <h5
                        class="fw-bold {{ ($incomeStatement['totals']['net_income'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                        Rp {{ number_format($incomeStatement['totals']['net_income'] ?? 0, 0, ',', '.') }}
                    </h5>
                </div>

                @if(isset($incomeStatement['totals']['profit_margin']))
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="text-muted">Margin Laba:</span>
                    <span
                        class="fw-bold {{ $incomeStatement['totals']['profit_margin'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($incomeStatement['totals']['profit_margin'], 2) }}%
                    </span>
                </div>
                @endif
            </div>

            <!-- Performance Analysis -->
            @if(isset($incomeStatement['totals']['total_revenue']) && $incomeStatement['totals']['total_revenue'] > 0)
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title">Analisis Kinerja</h6>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">Rasio Beban:</small>
                                    <div class="fw-bold">
                                        {{ number_format((($incomeStatement['totals']['total_expenses'] ?? 0) / $incomeStatement['totals']['total_revenue']) * 100, 2) }}%
                                    </div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Efisiensi:</small>
                                    <div
                                        class="fw-bold {{ $incomeStatement['totals']['profit_margin'] >= 10 ? 'text-success' : ($incomeStatement['totals']['profit_margin'] >= 0 ? 'text-warning' : 'text-danger') }}">
                                        @if($incomeStatement['totals']['profit_margin'] >= 10)
                                        Sangat Baik
                                        @elseif($incomeStatement['totals']['profit_margin'] >= 5)
                                        Baik
                                        @elseif($incomeStatement['totals']['profit_margin'] >= 0)
                                        Cukup
                                        @else
                                        Perlu Perbaikan
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title">Rekomendasi</h6>
                            @if(($incomeStatement['totals']['profit_margin'] ?? 0) < 5) <small class="text-warning">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Pertimbangkan untuk meninjau struktur biaya dan meningkatkan efisiensi operasional.
                                </small>
                                @elseif(($incomeStatement['totals']['profit_margin'] ?? 0) >= 10)
                                <small class="text-success">
                                    <i class="bi bi-check-circle me-1"></i>
                                    Kinerja keuangan sangat baik. Pertahankan strategi yang ada.
                                </small>
                                @else
                                <small class="text-info">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Kinerja cukup baik. Cari peluang untuk meningkatkan pendapatan.
                                </small>
                                @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Report Footer -->
            <div class="row mt-5">
                <div class="col-md-6">
                    <div class="text-center">
                        <p class="mb-5">Disiapkan oleh:</p>
                        <div class="border-top pt-2" style="width: 200px; margin: 0 auto;">
                            <strong>{{ auth()->user()->name }}</strong><br>
                            <small>{{ auth()->user()->roles->first()->name ?? 'Staff' }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-center">
                        <p class="mb-5">Disetujui oleh:</p>
                        <div class="border-top pt-2" style="width: 200px; margin: 0 auto;">
                            <strong>_________________</strong><br>
                            <small>Manager Keuangan</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <small class="text-muted">
                    Laporan digenerate pada {{ now()->format('d F Y H:i:s') }} oleh sistem HERMES
                </small>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function incomeStatementReport() {
    return {
        init() {
            // Initialize any required functionality
        }
    }
}
</script>
@endpush

@push('styles')
<style>
    @media print {

        .btn-toolbar,
        .card-shadow,
        .navbar,
        .sidebar {
            display: none !important;
        }

        .card {
            border: none !important;
            box-shadow: none !important;
        }

        .card-body {
            padding: 0 !important;
        }

        body {
            background: white !important;
        }
    }
</style>
@endpush
@endsection
