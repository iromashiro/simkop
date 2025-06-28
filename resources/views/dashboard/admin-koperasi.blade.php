{{-- resources/views/dashboard/admin-koperasi.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard Admin Koperasi')

@php
$header = [
'title' => 'Dashboard ' . auth()->user()->cooperative->name,
'subtitle' => 'Kelola laporan keuangan koperasi Anda'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')]
];
@endphp

@section('content')
<div class="row">
    <!-- Quick Stats -->
    <div class="col-md-3 mb-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-1">{{ $stats['total_reports'] ?? 0 }}</h3>
                        <p class="mb-0">Total Laporan</p>
                    </div>
                    <i class="bi bi-file-earmark-text fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-1">{{ $stats['approved_reports'] ?? 0 }}</h3>
                        <p class="mb-0">Laporan Disetujui</p>
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
                        <p class="mb-0">Menunggu Persetujuan</p>
                    </div>
                    <i class="bi bi-clock fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-1">{{ $stats['draft_reports'] ?? 0 }}</h3>
                        <p class="mb-0">Draft Laporan</p>
                    </div>
                    <i class="bi bi-file-earmark fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-lightning me-2"></i>
                    Aksi Cepat
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="{{ route('financial.balance-sheet.create') }}"
                            class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                            <i class="bi bi-clipboard-data fs-1 mb-2"></i>
                            <span>Buat Laporan Posisi Keuangan</span>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="{{ route('financial.income-statement.create') }}"
                            class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                            <i class="bi bi-bar-chart fs-1 mb-2"></i>
                            <span>Buat Laporan Laba Rugi</span>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="{{ route('financial.cash-flow.create') }}"
                            class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                            <i class="bi bi-cash-stack fs-1 mb-2"></i>
                            <span>Buat Laporan Arus Kas</span>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="{{ route('reports.export.index') }}"
                            class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                            <i class="bi bi-download fs-1 mb-2"></i>
                            <span>Ekspor Laporan</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Reports -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Laporan Terbaru
                </h5>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary active" data-filter="all">Semua</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="draft">Draft</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="submitted">Terkirim</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="approved">Disetujui</button>
                </div>
            </div>
            <div class="card-body">
                @if($recentReports->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Jenis Laporan</th>
                                <th>Tahun</th>
                                <th>Status</th>
                                <th>Terakhir Update</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentReports as $report)
                            <tr data-status="{{ $report->status }}">
                                <td>
                                    <div class="fw-bold">{{ ucwords(str_replace('_', ' ', $report->report_type)) }}
                                    </div>
                                    <small
                                        class="text-muted">{{ $report->notes ? Str::limit($report->notes, 30) : 'Tidak ada catatan' }}</small>
                                </td>
                                <td>{{ $report->reporting_year }}</td>
                                <td>
                                    @switch($report->status)
                                    @case('draft')
                                    <span class="badge bg-secondary">Draft</span>
                                    @break
                                    @case('submitted')
                                    <span class="badge bg-warning">Terkirim</span>
                                    @break
                                    @case('approved')
                                    <span class="badge bg-success">Disetujui</span>
                                    @break
                                    @case('rejected')
                                    <span class="badge bg-danger">Ditolak</span>
                                    @break
                                    @endswitch
                                </td>
                                <td>{{ $report->updated_at->diffForHumans() }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('financial.' . str_replace('_', '-', $report->report_type) . '.show', $report) }}"
                                            class="btn btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if($report->status === 'draft')
                                        <a href="{{ route('financial.' . str_replace('_', '-', $report->report_type) . '.edit', $report) }}"
                                            class="btn btn-outline-secondary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @endif
                                        @if(in_array($report->status, ['approved']))
                                        <a href="{{ route('financial.' . str_replace('_', '-', $report->report_type) . '.export', [$report, 'pdf']) }}"
                                            class="btn btn-outline-success">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4">
                    <i class="bi bi-file-earmark-text fs-1 text-muted"></i>
                    <p class="text-muted mt-2">Belum ada laporan yang dibuat</p>
                    <a href="{{ route('financial.balance-sheet.create') }}" class="btn btn-primary">
                        Buat Laporan Pertama
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Notifications & Reminders -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-bell me-2"></i>
                    Notifikasi & Pengingat
                </h5>
            </div>
            <div class="card-body">
                @if($notifications->count() > 0)
                @foreach($notifications->take(5) as $notification)
                <div class="d-flex mb-3 {{ $notification->read_at ? '' : 'bg-light p-2 rounded' }}">
                    <div class="flex-shrink-0 me-3">
                        <div class="bg-{{ $notification->type === 'success' ? 'success' : ($notification->type === 'warning' ? 'warning' : 'info') }} text-white rounded-circle d-flex align-items-center justify-content-center"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-{{ $notification->icon ?? 'bell' }}"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold small">{{ $notification->title }}</div>
                        <div class="text-muted small">{{ Str::limit($notification->message, 50) }}</div>
                        <div class="text-muted small">{{ $notification->created_at->diffForHumans() }}</div>
                    </div>
                </div>
                @endforeach

                <div class="text-center mt-3">
                    <a href="{{ route('notifications.index') }}" class="btn btn-outline-primary btn-sm">
                        Lihat Semua Notifikasi
                    </a>
                </div>
                @else
                <div class="text-center py-4">
                    <i class="bi bi-bell-slash fs-1 text-muted"></i>
                    <p class="text-muted mt-2">Tidak ada notifikasi</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Reporting Calendar -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-check me-2"></i>
                    Jadwal Pelaporan {{ date('Y') }}
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach(['Q1' => 'Maret', 'Q2' => 'Juni', 'Q3' => 'September', 'Q4' => 'Desember'] as $quarter =>
                    $month)
                    <div class="col-md-3 mb-3">
                        <div class="card border-{{ $reportingStatus[$quarter] ?? 'secondary' }}">
                            <div class="card-body text-center">
                                <h6 class="card-title">{{ $quarter }} - {{ $month }}</h6>
                                @if(($reportingStatus[$quarter] ?? 'pending') === 'completed')
                                <i class="bi bi-check-circle text-success fs-1"></i>
                                <p class="text-success small mt-2">Selesai</p>
                                @elseif(($reportingStatus[$quarter] ?? 'pending') === 'in_progress')
                                <i class="bi bi-clock text-warning fs-1"></i>
                                <p class="text-warning small mt-2">Dalam Proses</p>
                                @else
                                <i class="bi bi-circle text-muted fs-1"></i>
                                <p class="text-muted small mt-2">Belum Dimulai</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Report Filter
document.querySelectorAll('[data-filter]').forEach(button => {
    button.addEventListener('click', function() {
        const filter = this.dataset.filter;

        // Update active button
        document.querySelectorAll('[data-filter]').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');

        // Filter table rows
        document.querySelectorAll('tbody tr[data-status]').forEach(row => {
            if (filter === 'all' || row.dataset.status === filter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>
@endpush
