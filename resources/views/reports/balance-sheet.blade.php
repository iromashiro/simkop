@extends('layouts.app')

@section('title', 'Laporan Neraca')

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-bar-chart me-2"></i>
    Laporan Neraca
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
                    href="{{ route('reports.balance-sheet.export', ['format' => 'pdf', 'as_of_date' => request('as_of_date')]) }}">
                    <i class="bi bi-file-earmark-pdf me-2"></i>PDF
                </a></li>
            <li><a class="dropdown-item"
                    href="{{ route('reports.balance-sheet.export', ['format' => 'excel', 'as_of_date' => request('as_of_date')]) }}">
                    <i class="bi bi-file-earmark-excel me-2"></i>Excel
                </a></li>
        </ul>
    </div>
</div>
@endsection

@section('content')
<div x-data="balanceSheetReport()" x-init="init()">
    <!-- Report Parameters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.balance-sheet') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="as_of_date" class="form-label">Per Tanggal</label>
                    <input type="date" class="form-control" id="as_of_date" name="as_of_date"
                        value="{{ request('as_of_date', now()->format('Y-m-d')) }}">
                </div>

                <div class="col-md-4">
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

                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-refresh me-1"></i>
                        Update Laporan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Balance Sheet Report -->
    <div class="card shadow" id="balance-sheet-report">
        <div class="card-body">
            <!-- Report Header -->
            <div class="text-center mb-4">
                <h3 class="fw-bold">{{ auth()->user()->primaryCooperative()->name ?? 'HERMES Koperasi' }}</h3>
                <h4>NERACA</h4>
                <h5>Per {{ \Carbon\Carbon::parse(request('as_of_date', now()))->format('d F Y') }}</h5>
            </div>

            <div class="row">
                <!-- Assets -->
                <div class="col-md-6">
                    <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">ASET</h5>

                    @if(isset($balanceSheet['assets']) && count($balanceSheet['assets']) > 0)
                    @foreach($balanceSheet['assets'] as $asset)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="fw-bold">{{ $asset['code'] }}</span>
                            <span>{{ $asset['name'] }}</span>
                        </div>
                        <span class="fw-bold">
                            Rp {{ number_format($asset['balance'], 0, ',', '.') }}
                        </span>
                    </div>
                    @endforeach

                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold">TOTAL ASET</h6>
                        <h6 class="fw-bold text-primary">
                            Rp {{ number_format($balanceSheet['totals']['total_assets'] ?? 0, 0, ',', '.') }}
                        </h6>
                    </div>
                    @else
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox display-4"></i>
                        <p class="mt-2">Tidak ada data aset</p>
                    </div>
                    @endif
                </div>

                <!-- Liabilities and Equity -->
                <div class="col-md-6">
                    <!-- Liabilities -->
                    <h5 class="fw-bold text-warning border-bottom pb-2 mb-3">KEWAJIBAN</h5>

                    @if(isset($balanceSheet['liabilities']) && count($balanceSheet['liabilities']) > 0)
                    @foreach($balanceSheet['liabilities'] as $liability)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="fw-bold">{{ $liability['code'] }}</span>
                            <span>{{ $liability['name'] }}</span>
                        </div>
                        <span class="fw-bold">
                            Rp {{ number_format($liability['balance'], 0, ',', '.') }}
                        </span>
                    </div>
                    @endforeach

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold">Total Kewajiban</h6>
                        <h6 class="fw-bold text-warning">
                            Rp {{ number_format($balanceSheet['totals']['total_liabilities'] ?? 0, 0, ',', '.') }}
                        </h6>
                    </div>
                    @else
                    <div class="text-center text-muted py-2">
                        <p>Tidak ada data kewajiban</p>
                    </div>
                    @endif

                    <!-- Equity -->
                    <h5 class="fw-bold text-success border-bottom pb-2 mb-3">MODAL</h5>

                    @if(isset($balanceSheet['equity']) && count($balanceSheet['equity']) > 0)
                    @foreach($balanceSheet['equity'] as $equity)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="fw-bold">{{ $equity['code'] }}</span>
                            <span>{{ $equity['name'] }}</span>
                        </div>
                        <span class="fw-bold">
                            Rp {{ number_format($equity['balance'], 0, ',', '.') }}
                        </span>
                    </div>
                    @endforeach

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold">Total Modal</h6>
                        <h6 class="fw-bold text-success">
                            Rp {{ number_format($balanceSheet['totals']['total_equity'] ?? 0, 0, ',', '.') }}
                        </h6>
                    </div>
                    @else
                    <div class="text-center text-muted py-2">
                        <p>Tidak ada data modal</p>
                    </div>
                    @endif

                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold">TOTAL KEWAJIBAN & MODAL</h6>
                        <h6 class="fw-bold text-primary">
                            Rp
                            {{ number_format(($balanceSheet['totals']['total_liabilities'] ?? 0) + ($balanceSheet['totals']['total_equity'] ?? 0), 0, ',', '.') }}
                        </h6>
                    </div>
                </div>
            </div>

            <!-- Balance Check -->
            @if(isset($balanceSheet['totals']['balance_check']) && $balanceSheet['totals']['balance_check'] != 0)
            <div class="alert alert-warning mt-4">
                <h6 class="alert-heading">Peringatan: Neraca Tidak Seimbang</h6>
                <p class="mb-0">
                    Selisih: Rp {{ number_format(abs($balanceSheet['totals']['balance_check']), 0, ',', '.') }}
                    <br>
                    <small>Mohon periksa kembali pencatatan jurnal untuk memastikan keseimbangan debit dan
                        kredit.</small>
                </p>
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
    function balanceSheetReport() {
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
