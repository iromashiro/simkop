@extends('layouts.app')

@section('title', 'Laporan Keuangan')

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-graph-up me-2"></i>
    Laporan Keuangan
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-calendar3 me-1"></i>
            Periode Fiskal
        </button>
        <ul class="dropdown-menu">
            @foreach($fiscalPeriods as $period)
            <li><a class="dropdown-item" href="?fiscal_period={{ $period->id }}">
                    {{ $period->name }}
                    @if($period->is_current)
                    <span class="badge bg-primary ms-2">Aktif</span>
                    @endif
                </a></li>
            @endforeach
        </ul>
    </div>
</div>
@endsection

@section('content')
<div x-data="reportManagement()" x-init="init()">
    <!-- Quick Report Cards -->
    <div class="row mb-4">
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-primary text-white">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-bar-chart me-2"></i>
                        Neraca
                    </h6>
                </div>
                <div class="card-body">
                    <p class="card-text">Laporan posisi keuangan koperasi pada tanggal tertentu, menampilkan aset,
                        kewajiban, dan modal.</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('reports.balance-sheet') }}" class="btn btn-primary">
                            <i class="bi bi-eye me-1"></i>
                            Lihat Laporan
                        </a>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                                data-bs-toggle="dropdown">
                                <i class="bi bi-download me-1"></i>
                                Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item"
                                        href="{{ route('reports.balance-sheet.export', ['format' => 'pdf']) }}">
                                        <i class="bi bi-file-earmark-pdf me-2"></i>PDF
                                    </a></li>
                                <li><a class="dropdown-item"
                                        href="{{ route('reports.balance-sheet.export', ['format' => 'excel']) }}">
                                        <i class="bi bi-file-earmark-excel me-2"></i>Excel
                                    </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-success text-white">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-graph-up me-2"></i>
                        Laporan Laba Rugi
                    </h6>
                </div>
                <div class="card-body">
                    <p class="card-text">Laporan pendapatan dan beban koperasi selama periode tertentu untuk mengetahui
                        laba atau rugi.</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('reports.income-statement') }}" class="btn btn-success">
                            <i class="bi bi-eye me-1"></i>
                            Lihat Laporan
                        </a>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                                data-bs-toggle="dropdown">
                                <i class="bi bi-download me-1"></i>
                                Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item"
                                        href="{{ route('reports.income-statement.export', ['format' => 'pdf']) }}">
                                        <i class="bi bi-file-earmark-pdf me-2"></i>PDF
                                    </a></li>
                                <li><a class="dropdown-item"
                                        href="{{ route('reports.income-statement.export', ['format' => 'excel']) }}">
                                        <i class="bi bi-file-earmark-excel me-2"></i>Excel
                                    </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-info text-white">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-cash-stack me-2"></i>
                        Laporan Arus Kas
                    </h6>
                </div>
                <div class="card-body">
                    <p class="card-text">Laporan arus masuk dan keluar kas dari aktivitas operasional, investasi, dan
                        pendanaan.</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('reports.cash-flow') }}" class="btn btn-info">
                            <i class="bi bi-eye me-1"></i>
                            Lihat Laporan
                        </a>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                                data-bs-toggle="dropdown">
                                <i class="bi bi-download me-1"></i>
                                Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item"
                                        href="{{ route('reports.cash-flow.export', ['format' => 'pdf']) }}">
                                        <i class="bi bi-file-earmark-pdf me-2"></i>PDF
                                    </a></li>
                                <li><a class="dropdown-item"
                                        href="{{ route('reports.cash-flow.export', ['format' => 'excel']) }}">
                                        <i class="bi bi-file-earmark-excel me-2"></i>Excel
                                    </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Operational Reports -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-clipboard-data me-2"></i>
                        Laporan Operasional
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-left-warning h-100">
                                <div class="card-body">
                                    <h6 class="card-title">Laporan Anggota</h6>
                                    <p class="card-text small">Data lengkap anggota, statistik keanggotaan, dan analisis
                                        pertumbuhan.</p>
                                    <a href="{{ route('reports.members') }}" class="btn btn-sm btn-warning">
                                        <i class="bi bi-people me-1"></i>
                                        Lihat
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-left-success h-100">
                                <div class="card-body">
                                    <h6 class="card-title">Laporan Simpanan</h6>
                                    <p class="card-text small">Rekap simpanan anggota, pertumbuhan dana, dan analisis
                                        produk simpanan.</p>
                                    <a href="{{ route('reports.savings') }}" class="btn btn-sm btn-success">
                                        <i class="bi bi-piggy-bank me-1"></i>
                                        Lihat
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-left-info h-100">
                                <div class="card-body">
                                    <h6 class="card-title">Laporan Pinjaman</h6>
                                    <p class="card-text small">Portfolio pinjaman, tingkat kolektibilitas, dan analisis
                                        risiko kredit.</p>
                                    <a href="{{ route('reports.loans') }}" class="btn btn-sm btn-info">
                                        <i class="bi bi-cash-stack me-1"></i>
                                        Lihat
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card border-left-danger h-100">
                                <div class="card-body">
                                    <h6 class="card-title">Laporan SHU</h6>
                                    <p class="card-text small">Perhitungan dan distribusi Sisa Hasil Usaha kepada
                                        anggota.</p>
                                    <a href="{{ route('reports.shu') }}" class="btn btn-sm btn-danger">
                                        <i class="bi bi-pie-chart me-1"></i>
                                        Lihat
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Report Builder -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-tools me-2"></i>
                        Pembuat Laporan Kustom
                    </h6>
                </div>
                <div class="card-body">
                    <form @submit.prevent="generateCustomReport" class="row g-3">
                        <div class="col-md-3">
                            <label for="report_type" class="form-label">Jenis Laporan</label>
                            <select class="form-select" id="report_type" x-model="customReport.type" required>
                                <option value="">Pilih Jenis Laporan</option>
                                <option value="balance_sheet">Neraca</option>
                                <option value="income_statement">Laba Rugi</option>
                                <option value="cash_flow">Arus Kas</option>
                                <option value="trial_balance">Neraca Saldo</option>
                                <option value="general_ledger">Buku Besar</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="start_date" class="form-label">Dari Tanggal</label>
                            <input type="date" class="form-control" id="start_date" x-model="customReport.startDate"
                                required>
                        </div>

                        <div class="col-md-2">
                            <label for="end_date" class="form-label">Sampai Tanggal</label>
                            <input type="date" class="form-control" id="end_date" x-model="customReport.endDate"
                                required>
                        </div>

                        <div class="col-md-2">
                            <label for="format" class="form-label">Format</label>
                            <select class="form-select" id="format" x-model="customReport.format" required>
                                <option value="view">Lihat di Browser</option>
                                <option value="pdf">Download PDF</option>
                                <option value="excel">Download Excel</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary" :disabled="loading">
                                    <span x-show="!loading">
                                        <i class="bi bi-play-circle me-1"></i>
                                        Generate Laporan
                                    </span>
                                    <span x-show="loading">
                                        <span class="spinner-border spinner-border-sm me-2"></span>
                                        Memproses...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function reportManagement() {
    return {
        loading: false,
        customReport: {
            type: '',
            startDate: '{{ now()->startOfYear()->format('Y-m-d') }}',
            endDate: '{{ now()->format('Y-m-d') }}',
            format: 'view'
        },

        init() {
            // Initialize any required functionality
        },

        async generateCustomReport() {
            if (!this.customReport.type || !this.customReport.startDate || !this.customReport.endDate) {
                HERMES.utils.showToast('Mohon lengkapi semua field yang diperlukan', 'warning');
                return;
            }

            this.loading = true;

            try {
                const params = new URLSearchParams({
                    type: this.customReport.type,
                    start_date: this.customReport.startDate,
                    end_date: this.customReport.endDate,
                    format: this.customReport.format
                });

                if (this.customReport.format === 'view') {
                    // Navigate to report view
                    window.location.href = `/reports/custom?${params.toString()}`;
                } else {
                    // Download file
                    window.location.href = `/reports/custom/export?${params.toString()}`;
                    HERMES.utils.showToast('Laporan sedang diunduh...', 'info');
                }
            } catch (error) {
                console.error('Error:', error);
                HERMES.utils.showToast('Terjadi kesalahan saat generate laporan', 'error');
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

    .border-left-danger {
        border-left: 0.25rem solid #e74a3b !important;
    }
</style>
@endpush
@endsection
