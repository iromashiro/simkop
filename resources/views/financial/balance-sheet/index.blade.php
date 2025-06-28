{{-- resources/views/financial/balance-sheet/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Neraca')

@php
$header = [
'title' => 'Neraca',
'subtitle' => 'Laporan posisi keuangan koperasi',
'actions' => '<a href="' . route('financial.balance-sheet.create') . '" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i> Buat Neraca Baru
</a>'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Neraca', 'url' => route('financial.balance-sheet.index')]
];
@endphp

@section('content')
<!-- Filter & Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('financial.balance-sheet.index') }}" class="row g-3">
            <div class="col-md-3">
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
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Semua Status</option>
                    <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="submitted" {{ $status === 'submitted' ? 'selected' : '' }}>Terkirim</option>
                    <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Disetujui</option>
                    <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Ditolak</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Cari</label>
                <input type="text" class="form-control" name="search" value="{{ $search }}"
                    placeholder="Cari berdasarkan catatan...">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                    <a href="{{ route('financial.balance-sheet.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Balance Sheet List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-clipboard-data me-2"></i>
            Daftar Neraca ({{ $reports->total() }} laporan)
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
                        <th>Tahun</th>
                        <th>Total Aset</th>
                        <th>Total Kewajiban</th>
                        <th>Total Ekuitas</th>
                        <th>Status</th>
                        <th>Terakhir Update</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reports as $report)
                    <tr>
                        <td>
                            <div class="fw-bold fs-5">{{ $report->reporting_year }}</div>
                            <small class="text-muted">Periode {{ $report->reporting_year }}</small>
                        </td>
                        <td>
                            <div class="fw-bold text-primary">
                                Rp {{ number_format($report->total_assets ?? 0, 0, ',', '.') }}
                            </div>
                            <small class="text-muted">Total Aset</small>
                        </td>
                        <td>
                            <div class="fw-bold text-warning">
                                Rp {{ number_format($report->total_liabilities ?? 0, 0, ',', '.') }}
                            </div>
                            <small class="text-muted">Total Kewajiban</small>
                        </td>
                        <td>
                            <div class="fw-bold text-success">
                                Rp {{ number_format($report->total_equity ?? 0, 0, ',', '.') }}
                            </div>
                            <small class="text-muted">Total Ekuitas</small>
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
                            @break
                            @case('rejected')
                            <span class="badge bg-danger">Ditolak</span>
                            @break
                            @endswitch
                        </td>
                        <td>
                            <div>{{ $report->updated_at->format('d/m/Y') }}</div>
                            <small class="text-muted">{{ $report->updated_at->format('H:i') }}</small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('financial.balance-sheet.show', $report->reporting_year) }}"
                                    class="btn btn-outline-primary" title="Lihat">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($report->status === 'draft')
                                <a href="{{ route('financial.balance-sheet.edit', $report->reporting_year) }}"
                                    class="btn btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-success"
                                    onclick="submitReport({{ $report->reporting_year }})" title="Kirim">
                                    <i class="bi bi-send"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger"
                                    onclick="deleteReport({{ $report->reporting_year }})" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @endif
                                @if($report->status === 'approved')
                                <a href="{{ route('reports.export.pdf', ['type' => 'balance-sheet', 'year' => $report->reporting_year]) }}"
                                    class="btn btn-outline-info" title="Download PDF">
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
            <i class="bi bi-clipboard-data fs-1 text-muted"></i>
            <h5 class="text-muted mt-3">Belum ada laporan neraca</h5>
            @if($search || $year || $status)
            <p class="text-muted">Tidak ada hasil untuk filter yang dipilih</p>
            <a href="{{ route('financial.balance-sheet.index') }}" class="btn btn-outline-primary">
                Lihat Semua Laporan
            </a>
            @else
            <p class="text-muted">Mulai buat laporan neraca pertama Anda</p>
            <a href="{{ route('financial.balance-sheet.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Buat Neraca Pertama
            </a>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    function submitReport(year) {
    if (confirm('Apakah Anda yakin ingin mengirim laporan neraca tahun ' + year + '? Setelah dikirim, laporan tidak dapat diubah.')) {
        fetch(`/financial/balance-sheet/${year}/submit`, {
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
                alert('Gagal mengirim laporan: ' + data.message);
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan: ' + error.message);
        });
    }
}

function deleteReport(year) {
    if (SIMKOP.confirmDelete('Apakah Anda yakin ingin menghapus laporan neraca tahun ' + year + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/financial/balance-sheet/${year}`;
        form.innerHTML = `
            <input type="hidden" name="_token" value="${SIMKOP.csrfToken}">
            <input type="hidden" name="_method" value="DELETE">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.open(`${window.location.pathname}?${params.toString()}`, '_blank');
}
</script>
@endpush
