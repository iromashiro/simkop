{{-- resources/views/dashboard/admin-dinas.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard Admin Dinas')

@php
$header = [
'title' => 'Dashboard Admin Dinas',
'subtitle' => 'Selamat datang di Sistem Informasi Manajemen Koperasi'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')]
];
@endphp

@section('content')
<div class="row">
    <!-- Statistics Cards -->
    <div class="col-md-3 mb-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-1">{{ $stats['total_cooperatives'] ?? 0 }}</h3>
                        <p class="mb-0">Total Koperasi</p>
                    </div>
                    <i class="bi bi-building fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-1">{{ $stats['active_cooperatives'] ?? 0 }}</h3>
                        <p class="mb-0">Koperasi Aktif</p>
                    </div>
                    <i class="bi bi-check-circle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-1">{{ $stats['pending_reports'] ?? 0 }}</h3>
                        <p class="mb-0">Laporan Menunggu</p>
                    </div>
                    <i class="bi bi-clock fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-1">{{ $stats['total_users'] ?? 0 }}</h3>
                        <p class="mb-0">Total Pengguna</p>
                    </div>
                    <i class="bi bi-people fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Pending Reports -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-clipboard-check me-2"></i>
                    Laporan Menunggu Persetujuan
                </h5>
                <a href="{{ route('admin.reports.approval') }}" class="btn btn-primary btn-sm">
                    Lihat Semua
                </a>
            </div>
            <div class="card-body">
                @if($pendingReports->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Koperasi</th>
                                <th>Jenis Laporan</th>
                                <th>Tahun</th>
                                <th>Tanggal Kirim</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingReports->take(5) as $report)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $report->cooperative->name }}</div>
                                    <small class="text-muted">{{ $report->cooperative->registration_number }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        {{ ucwords(str_replace('_', ' ', $report->report_type)) }}
                                    </span>
                                </td>
                                <td>{{ $report->reporting_year }}</td>
                                <td>{{ $report->submitted_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.reports.show', $report) }}"
                                            class="btn btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-success"
                                            onclick="approveReport({{ $report->id }})">
                                            <i class="bi bi-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger"
                                            onclick="rejectReport({{ $report->id }})">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4">
                    <i class="bi bi-clipboard-check fs-1 text-muted"></i>
                    <p class="text-muted mt-2">Tidak ada laporan yang menunggu persetujuan</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-activity me-2"></i>
                    Aktivitas Terbaru
                </h5>
            </div>
            <div class="card-body">
                @if($recentActivities->count() > 0)
                @foreach($recentActivities->take(5) as $activity)
                <div class="d-flex mb-3">
                    <div class="flex-shrink-0 me-3">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-{{ $activity->icon ?? 'activity' }}"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold small">{{ $activity->description }}</div>
                        <div class="text-muted small">{{ $activity->cooperative->name ?? 'System' }}</div>
                        <div class="text-muted small">{{ $activity->created_at->diffForHumans() }}</div>
                    </div>
                </div>
                @endforeach
                @else
                <div class="text-center py-4">
                    <i class="bi bi-activity fs-1 text-muted"></i>
                    <p class="text-muted mt-2">Belum ada aktivitas</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Cooperative Status Chart -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-pie-chart me-2"></i>
                    Status Koperasi
                </h5>
            </div>
            <div class="card-body">
                <canvas id="cooperativeStatusChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Monthly Reports Chart -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-bar-chart me-2"></i>
                    Laporan Bulanan
                </h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyReportsChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Cooperative Status Chart
const cooperativeStatusCtx = document.getElementById('cooperativeStatusChart').getContext('2d');
new Chart(cooperativeStatusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Aktif', 'Tidak Aktif', 'Pending'],
        datasets: [{
            data: [
                {{ $stats['active_cooperatives'] ?? 0 }},
                {{ $stats['inactive_cooperatives'] ?? 0 }},
                {{ $stats['pending_cooperatives'] ?? 0 }}
            ],
            backgroundColor: ['#198754', '#dc3545', '#ffc107']
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

// Monthly Reports Chart
const monthlyReportsCtx = document.getElementById('monthlyReportsChart').getContext('2d');
new Chart(monthlyReportsCtx, {
    type: 'bar',
    data: {
        labels: {!! json_encode($monthlyReportsLabels ?? []) !!},
        datasets: [{
            label: 'Laporan Diterima',
            data: {!! json_encode($monthlyReportsData ?? []) !!},
            backgroundColor: '#0d6efd'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Report Actions
function approveReport(reportId) {
    if (confirm('Apakah Anda yakin ingin menyetujui laporan ini?')) {
        fetch(`/admin/reports/${reportId}/approve`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': SIMKOP.csrfToken,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Gagal menyetujui laporan: ' + data.message);
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan: ' + error.message);
        });
    }
}

function rejectReport(reportId) {
    const reason = prompt('Masukkan alasan penolakan:');
    if (reason) {
        fetch(`/admin/reports/${reportId}/reject`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': SIMKOP.csrfToken,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ rejection_reason: reason })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Gagal menolak laporan: ' + data.message);
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan: ' + error.message);
        });
    }
}
</script>
@endpush
