@extends('layouts.app')

@section('title', 'Dashboard')

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-speedometer2 me-2"></i>
    Dashboard
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    <div class="btn-group me-2">
        <button type="button" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-calendar3"></i>
            {{ now()->format('d M Y') }}
        </button>
    </div>
</div>
@endsection

@section('content')
<div x-data="dashboardData()" x-init="init()">
    <!-- Quick Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Anggota
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($quickStats['total_members']) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
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
                                Total Simpanan
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp {{ number_format($quickStats['total_savings'], 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-piggy-bank text-success" style="font-size: 2rem;"></i>
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
                                Pinjaman Aktif
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($quickStats['active_loans']) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-cash-stack text-info" style="font-size: 2rem;"></i>
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
                                {{ number_format($quickStats['pending_approvals']) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Financial Overview Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Ringkasan Keuangan</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots-vertical text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow">
                            <a class="dropdown-item" href="#">Export Data</a>
                            <a class="dropdown-item" href="#">Lihat Detail</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="financialChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pie Chart -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Distribusi Aset</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="assetChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Aktivitas Terbaru</h6>
                </div>
                <div class="card-body">
                    @if(count($recentActivities) > 0)
                    @foreach($recentActivities as $activity)
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                                style="width: 40px; height: 40px;">
                                <i class="bi bi-activity text-white"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold">{{ $activity['description'] }}</div>
                            <div class="text-muted small">
                                {{ $activity['created_at']->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                    @endforeach
                    @else
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox display-4"></i>
                        <p class="mt-2">Belum ada aktivitas</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Aksi Cepat</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        @can('create_members')
                        <div class="col-6 mb-3">
                            <a href="{{ route('members.create') }}" class="btn btn-outline-primary w-100">
                                <i class="bi bi-person-plus d-block mb-2" style="font-size: 1.5rem;"></i>
                                Tambah Anggota
                            </a>
                        </div>
                        @endcan

                        @can('create_loans')
                        <div class="col-6 mb-3">
                            <a href="{{ route('loans.create') }}" class="btn btn-outline-success w-100">
                                <i class="bi bi-cash-coin d-block mb-2" style="font-size: 1.5rem;"></i>
                                Pinjaman Baru
                            </a>
                        </div>
                        @endcan

                        @can('create_journal_entries')
                        <div class="col-6 mb-3">
                            <a href="{{ route('journal-entries.create') }}" class="btn btn-outline-info w-100">
                                <i class="bi bi-journal-plus d-block mb-2" style="font-size: 1.5rem;"></i>
                                Jurnal Baru
                            </a>
                        </div>
                        @endcan

                        @can('view_reports')
                        <div class="col-6 mb-3">
                            <a href="{{ route('reports.index') }}" class="btn btn-outline-warning w-100">
                                <i class="bi bi-graph-up d-block mb-2" style="font-size: 1.5rem;"></i>
                                Lihat Laporan
                            </a>
                        </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function dashboardData() {
    return {
        financialChart: null,
        assetChart: null,

        init() {
            this.initFinancialChart();
            this.initAssetChart();
        },

        initFinancialChart() {
            const ctx = document.getElementById('financialChart').getContext('2d');
            this.financialChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
                    datasets: [{
                        label: 'Simpanan',
                        data: @json($dashboard['savings_trend'] ?? [0,0,0,0,0,0]),
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.1
                    }, {
                        label: 'Pinjaman',
                        data: @json($dashboard['loans_trend'] ?? [0,0,0,0,0,0]),
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });
        },

        initAssetChart() {
            const ctx = document.getElementById('assetChart').getContext('2d');
            this.assetChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Simpanan', 'Pinjaman', 'Kas', 'Lainnya'],
                    datasets: [{
                        data: @json($dashboard['asset_distribution'] ?? [40, 30, 20, 10]),
                        backgroundColor: [
                            'rgb(54, 162, 235)',
                            'rgb(255, 99, 132)',
                            'rgb(255, 205, 86)',
                            'rgb(75, 192, 192)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
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

    .text-xs {
        font-size: 0.7rem;
    }

    .chart-area {
        position: relative;
        height: 300px;
    }

    .chart-pie {
        position: relative;
        height: 250px;
    }
</style>
@endpush
@endsection
