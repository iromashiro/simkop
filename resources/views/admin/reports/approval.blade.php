{{-- resources/views/admin/reports/approval.blade.php --}}
@extends('layouts.app')

@section('title', 'Persetujuan Laporan')

@php
$header = [
'title' => 'Persetujuan Laporan Keuangan',
'subtitle' => 'Review dan setujui laporan keuangan dari koperasi'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Persetujuan Laporan', 'url' => route('admin.reports.approval')]
];
@endphp

@section('content')
<!-- Filter & Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.reports.approval') }}" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Semua Status</option>
                    <option value="submitted" {{ $status === 'submitted' ? 'selected' : '' }}>Terkirim</option>
                    <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Disetujui</option>
                    <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Ditolak</option>
                    <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>Draft</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tahun</label>
                <select class="form-select" name="year">
                    <option value="">Semua Tahun</option>
                    @foreach($years as $yearOption)
                    <option value="{{ $yearOption }}" {{ $year == $yearOption ? 'selected' : '' }}>
                        {{ $yearOption }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Koperasi</label>
                <select class="form-select" name="cooperative">
                    <option value="">Semua Koperasi</option>
                    @foreach($cooperatives as $cooperativeOption)
                    <option value="{{ $cooperativeOption->id }}"
                        {{ $cooperative == $cooperativeOption->id ? 'selected' : '' }}>
                        {{ $cooperativeOption->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Jenis Laporan</label>
                <select class="form-select" name="report_type">
                    <option value="">Semua Jenis</option>
                    @foreach($reportTypes as $type)
                    <option value="{{ $type }}" {{ $reportType === $type ? 'selected' : '' }}>
                        {{ ucwords(str_replace('_', ' ', $type)) }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                    <a href="{{ route('admin.reports.approval') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Actions -->
@if($reports->where('status', 'submitted')->count() > 0)
<div class="card mb-4">
    <div class="card-body">
        <form id="bulkActionForm" method="POST">
            @csrf
            <div class="row align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Aksi Massal untuk Laporan Terpilih</label>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success" onclick="bulkApprove()">
                            <i class="bi bi-check-circle me-1"></i> Setujui Terpilih
                        </button>
                        <button type="button" class="btn btn-danger" onclick="bulkReject()">
                            <i class="bi bi-x-circle me-1"></i> Tolak Terpilih
                        </button>
                        <span class="text-muted align-self-center ms-2">
                            <span id="selectedCount">0</span> laporan terpilih
                        </span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label" for="selectAll">
                            Pilih Semua Laporan
                        </label>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

<!-- Reports Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-clipboard-check me-2"></i>
            Daftar Laporan ({{ $reports->total() }} laporan)
        </h5>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-success" onclick="exportData('excel')">
                <i class="bi bi-file-earmark-excel"></i> Excel
            </button>
            <button type="button" class="btn btn-outline-danger" onclick="exportData('pdf')">
                <i class="bi bi-file-earmark-pdf"></i> PDF
            </button>
        </div>
    </div>
    <div class="card-body">
        @if($reports->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="50">
                            @if($reports->where('status', 'submitted')->count() > 0)
                            <input type="checkbox" id="selectAllTable" class="form-check-input">
                            @endif
                        </th>
                        <th>Koperasi</th>
                        <th>Jenis Laporan</th>
                        <th>Tahun</th>
                        <th>Status</th>
                        <th>Tanggal Kirim</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reports as $report)
                    <tr>
                        <td>
                            @if($report->status === 'submitted')
                            <input type="checkbox" class="form-check-input report-checkbox" value="{{ $report->id }}"
                                name="report_ids[]">
                            @endif
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary text-white rounded d-flex align-items-center justify-content-center me-3"
                                    style="width: 35px; height: 35px;">
                                    {{ strtoupper(substr($report->cooperative->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="fw-bold">{{ $report->cooperative->name }}</div>
                                    <small class="text-muted">{{ $report->cooperative->code }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                {{ ucwords(str_replace('_', ' ', $report->report_type)) }}
                            </span>
                        </td>
                        <td>
                            <div class="fw-bold">{{ $report->reporting_year }}</div>
                        </td>
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
                            <div class="small text-muted">
                                {{ $report->approved_at?->format('d/m/Y H:i') }}
                            </div>
                            @break
                            @case('rejected')
                            <span class="badge bg-danger">Ditolak</span>
                            <div class="small text-muted">
                                {{ $report->rejected_at?->format('d/m/Y H:i') }}
                            </div>
                            @break
                            @endswitch
                        </td>
                        <td>
                            @if($report->submitted_at)
                            <div>{{ $report->submitted_at->format('d/m/Y') }}</div>
                            <small class="text-muted">{{ $report->submitted_at->format('H:i') }}</small>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('admin.reports.show', $report) }}" class="btn btn-outline-primary"
                                    title="Lihat Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($report->status === 'submitted')
                                <button type="button" class="btn btn-outline-success"
                                    onclick="approveReport({{ $report->id }})" title="Setujui">
                                    <i class="bi bi-check"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger"
                                    onclick="rejectReport({{ $report->id }})" title="Tolak">
                                    <i class="bi bi-x"></i>
                                </button>
                                @endif
                                @if($report->status === 'approved')
                                <a href="{{ route('reports.export.pdf', $report) }}" class="btn btn-outline-info"
                                    title="Download PDF">
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

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="text-muted">
                Menampilkan {{ $reports->firstItem() }} - {{ $reports->lastItem() }}
                dari {{ $reports->total() }} laporan
            </div>
            {{ $reports->appends(request()->query())->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="bi bi-clipboard-check fs-1 text-muted"></i>
            <h5 class="text-muted mt-3">Tidak ada laporan ditemukan</h5>
            @if($status || $year || $cooperative || $reportType)
            <p class="text-muted">Tidak ada hasil untuk filter yang dipilih</p>
            <a href="{{ route('admin.reports.approval') }}" class="btn btn-outline-primary">
                Lihat Semua Laporan
            </a>
            @else
            <p class="text-muted">Belum ada laporan yang dikirim untuk review</p>
            @endif
        </div>
        @endif
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tolak Laporan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rejectionForm">
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Alasan Penolakan <span
                                class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" required
                            placeholder="Jelaskan alasan penolakan laporan..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" onclick="submitRejection()">
                    <i class="bi bi-x-circle me-1"></i> Tolak Laporan
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let currentReportId = null;
let currentAction = null;

// Select all functionality
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.report-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateSelectedCount();
});

document.getElementById('selectAllTable')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.report-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateSelectedCount();
});

// Update selected count
document.querySelectorAll('.report-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateSelectedCount);
});

function updateSelectedCount() {
    const selected = document.querySelectorAll('.report-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = selected;
}

// Single report actions
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
    currentReportId = reportId;
    currentAction = 'single';
    document.getElementById('rejection_reason').value = '';
    new bootstrap.Modal(document.getElementById('rejectionModal')).show();
}

// Bulk actions
function bulkApprove() {
    const selected = document.querySelectorAll('.report-checkbox:checked');
    if (selected.length === 0) {
        alert('Pilih minimal satu laporan untuk disetujui.');
        return;
    }

    if (confirm(`Apakah Anda yakin ingin menyetujui ${selected.length} laporan terpilih?`)) {
        const reportIds = Array.from(selected).map(cb => cb.value);

        fetch('/admin/reports/bulk-approve', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': SIMKOP.csrfToken,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ report_ids: reportIds })
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

function bulkReject() {
    const selected = document.querySelectorAll('.report-checkbox:checked');
    if (selected.length === 0) {
        alert('Pilih minimal satu laporan untuk ditolak.');
        return;
    }

    currentAction = 'bulk';
    document.getElementById('rejection_reason').value = '';
    new bootstrap.Modal(document.getElementById('rejectionModal')).show();
}

function submitRejection() {
    const reason = document.getElementById('rejection_reason').value.trim();
    if (!reason) {
        alert('Alasan penolakan harus diisi.');
        return;
    }

    if (currentAction === 'single') {
        // Single rejection
        fetch(`/admin/reports/${currentReportId}/reject`, {
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
    } else {
        // Bulk rejection
        const selected = document.querySelectorAll('.report-checkbox:checked');
        const reportIds = Array.from(selected).map(cb => cb.value);

        fetch('/admin/reports/bulk-reject', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': SIMKOP.csrfToken,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                report_ids: reportIds,
                rejection_reason: reason
            })
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

    bootstrap.Modal.getInstance(document.getElementById('rejectionModal')).hide();
}

function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.open(`${window.location.pathname}?${params.toString()}`, '_blank');
}

// Initialize selected count
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
});
</script>
@endpush
