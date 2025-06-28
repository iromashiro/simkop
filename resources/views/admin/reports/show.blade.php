{{-- resources/views/admin/reports/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Detail Laporan')

@php
$header = [
'title' => 'Detail Laporan Keuangan',
'subtitle' => ucwords(str_replace('_', ' ', $report->report_type)) . ' - ' . $report->cooperative->name . ' (' .
$report->reporting_year . ')'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Persetujuan Laporan', 'url' => route('admin.reports.approval')],
['title' => 'Detail Laporan', 'url' => route('admin.reports.show', $report)]
];
@endphp

@section('content')
<div class="row">
    <!-- Report Information -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Informasi Laporan
                </h5>
                <div>
                    @switch($report->status)
                    @case('draft')
                    <span class="badge bg-secondary fs-6">Draft</span>
                    @break
                    @case('submitted')
                    <span class="badge bg-warning fs-6">Terkirim</span>
                    @break
                    @case('approved')
                    <span class="badge bg-success fs-6">Disetujui</span>
                    @break
                    @case('rejected')
                    <span class="badge bg-danger fs-6">Ditolak</span>
                    @break
                    @endswitch
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Jenis Laporan</label>
                        <div class="fw-bold fs-5">{{ ucwords(str_replace('_', ' ', $report->report_type)) }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Tahun Pelaporan</label>
                        <div class="fw-bold fs-5">{{ $report->reporting_year }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Koperasi</label>
                        <div class="fw-bold">{{ $report->cooperative->name }}</div>
                        <small class="text-muted">{{ $report->cooperative->code }}</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Alamat Koperasi</label>
                        <div class="fw-bold">{{ $report->cooperative->address }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Dibuat</label>
                        <div class="fw-bold">{{ $report->created_at->format('d F Y H:i') }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Terakhir Update</label>
                        <div class="fw-bold">{{ $report->updated_at->format('d F Y H:i') }}</div>
                    </div>
                    @if($report->submitted_at)
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Tanggal Kirim</label>
                        <div class="fw-bold">{{ $report->submitted_at->format('d F Y H:i') }}</div>
                    </div>
                    @endif
                    @if($report->approved_at)
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Tanggal Disetujui</label>
                        <div class="fw-bold">{{ $report->approved_at->format('d F Y H:i') }}</div>
                    </div>
                    @endif
                    @if($report->rejected_at)
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Tanggal Ditolak</label>
                        <div class="fw-bold">{{ $report->rejected_at->format('d F Y H:i') }}</div>
                    </div>
                    @endif
                    @if($report->rejection_reason)
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Alasan Penolakan</label>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            {{ $report->rejection_reason }}
                        </div>
                    </div>
                    @endif
                    @if($report->notes)
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Catatan</label>
                        <div class="fw-bold">{{ $report->notes }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Report Data Preview -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-table me-2"></i>
                    Preview Data Laporan
                </h5>
            </div>
            <div class="card-body">
                @if($report->data)
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-end">Nilai (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report->data as $key => $value)
                            @if(is_numeric($value))
                            <tr>
                                <td>{{ ucwords(str_replace('_', ' ', $key)) }}</td>
                                <td class="text-end font-monospace">{{ number_format($value, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4">
                    <i class="bi bi-file-earmark fs-1 text-muted"></i>
                    <p class="text-muted mt-2">Data laporan tidak tersedia</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Actions & Status -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-gear me-2"></i>
                    Aksi
                </h5>
            </div>
            <div class="card-body">
                @if($report->status === 'submitted')
                <div class="d-grid gap-2 mb-3">
                    <button type="button" class="btn btn-success btn-lg" onclick="approveReport({{ $report->id }})">
                        <i class="bi bi-check-circle me-2"></i>
                        Setujui Laporan
                    </button>
                    <button type="button" class="btn btn-danger btn-lg" onclick="rejectReport({{ $report->id }})">
                        <i class="bi bi-x-circle me-2"></i>
                        Tolak Laporan
                    </button>
                </div>
                <hr>
                @endif

                <div class="d-grid gap-2">
                    @if($report->status === 'approved')
                    <a href="{{ route('reports.export.pdf', $report) }}" class="btn btn-primary">
                        <i class="bi bi-download me-2"></i>
                        Download PDF
                    </a>
                    <a href="{{ route('reports.export.excel', $report) }}" class="btn btn-outline-success">
                        <i class="bi bi-file-earmark-excel me-2"></i>
                        Download Excel
                    </a>
                    @endif

                    <a href="{{ route('admin.reports.approval') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>
                        Kembali ke Daftar
                    </a>
                </div>
            </div>
        </div>

        <!-- Approval History -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    Riwayat Status
                </h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Laporan Dibuat</h6>
                            <p class="timeline-text">{{ $report->created_at->format('d F Y H:i') }}</p>
                        </div>
                    </div>

                    @if($report->submitted_at)
                    <div class="timeline-item">
                        <div class="timeline-marker bg-warning"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Laporan Dikirim</h6>
                            <p class="timeline-text">{{ $report->submitted_at->format('d F Y H:i') }}</p>
                        </div>
                    </div>
                    @endif

                    @if($report->approved_at)
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Laporan Disetujui</h6>
                            <p class="timeline-text">{{ $report->approved_at->format('d F Y H:i') }}</p>
                        </div>
                    </div>
                    @endif

                    @if($report->rejected_at)
                    <div class="timeline-item">
                        <div class="timeline-marker bg-danger"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Laporan Ditolak</h6>
                            <p class="timeline-text">{{ $report->rejected_at->format('d F Y H:i') }}</p>
                            @if($report->rejection_reason)
                            <p class="timeline-text text-muted small">{{ $report->rejection_reason }}</p>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
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

@push('styles')
<style>
    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 20px;
    }

    .timeline-marker {
        position: absolute;
        left: -22px;
        top: 0;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid white;
    }

    .timeline-content {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 3px solid #dee2e6;
    }

    .timeline-title {
        margin: 0 0 5px 0;
        font-size: 14px;
        font-weight: 600;
    }

    .timeline-text {
        margin: 0;
        font-size: 13px;
        color: #6c757d;
    }
</style>
@endpush

@push('scripts')
<script>
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
    document.getElementById('rejection_reason').value = '';
    new bootstrap.Modal(document.getElementById('rejectionModal')).show();
}

function submitRejection() {
    const reason = document.getElementById('rejection_reason').value.trim();
    if (!reason) {
        alert('Alasan penolakan harus diisi.');
        return;
    }

    fetch(`/admin/reports/{{ $report->id }}/reject`, {
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

    bootstrap.Modal.getInstance(document.getElementById('rejectionModal')).hide();
}
</script>
@endpush
