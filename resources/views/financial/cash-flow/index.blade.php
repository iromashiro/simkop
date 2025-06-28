{{-- resources/views/financial/cash-flow/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Laporan Arus Kas')

@php
$header = [
'title' => 'Laporan Arus Kas',
'subtitle' => 'Laporan pergerakan kas masuk dan keluar koperasi',
'actions' => '<a href="' . route('financial.cash-flow.create') . '" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i> Buat Laporan Baru
</a>'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Laporan Arus Kas', 'url' => route('financial.cash-flow.index')]
];
@endphp

@section('content')
<!-- Filter & Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('financial.cash-flow.index') }}" class="row g-3">
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
                    <a href="{{ route('financial.cash-flow.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Cash Flow List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-cash-stack me-2"></i>
            Daftar Laporan Arus Kas ({{ $reports->total() }} laporan)
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
                        <th>Kas Operasional</th>
                        <th>Kas Investasi</th>
                        <th>Kas Pendanaan</th>
                        <th>Kas Bersih</th>
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
                            <div
                                class="fw-bold {{ ($report->operating_cash_flow ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                Rp {{ number_format($report->operating_cash_flow ?? 0, 0, ',', '.') }}
                            </div>
                            <small class="text-muted">Aktivitas Operasional</small>
                        </td>
                        <td>
                            <div
                                class="fw-bold {{ ($report->investing_cash_flow ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                Rp {{ number_format($report->investing_cash_flow ?? 0, 0, ',', '.') }}
                            </div>
                            <small class="text-muted">Aktivitas Investasi</small>
                        </td>
                        <td>
                            <div
                                class="fw-bold {{ ($report->financing_cash_flow ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                Rp {{ number_format($report->financing_cash_flow ?? 0, 0, ',', '.') }}
                            </div>
                            <small class="text-muted">Aktivitas Pendanaan</small>
                        </td>
                        <td>
                            @php
                            $netCashFlow = ($report->operating_cash_flow ?? 0) + ($report->investing_cash_flow ?? 0) +
                            ($report->financing_cash_flow ?? 0);
                            @endphp
                            <div class="fw-bold {{ $netCashFlow >= 0 ? 'text-success' : 'text-danger' }}">
                                Rp {{ number_format($netCashFlow, 0, ',', '.') }}
                            </div>
                            <small class="text-muted">{{ $netCashFlow >= 0 ? 'Surplus' : 'Defisit' }} Kas</small>
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
                                <a href="{{ route('financial.cash-flow.show', $report) }}"
                                    class="btn btn-outline-primary" title="Lihat">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($report->status === 'draft')
                                <a href="{{ route('financial.cash-flow.edit', $report) }}"
                                    class="btn btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('financial.cash-flow.submit', $report) }}" method="POST"
                                    class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success"
                                        onclick="return confirm('Apakah Anda yakin ingin mengirim laporan ini?')"
                                        title="Kirim">
                                        <i class="bi bi-send"></i>
                                    </button>
                                </form>
                                <form action="{{ route('financial.cash-flow.destroy', $report) }}" method="POST"
                                    class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger"
                                        onclick="return confirm('Apakah Anda yakin ingin menghapus laporan ini?')"
                                        title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                @endif
                                @if($report->status === 'approved')
                                <a href="{{ route('reports.export.pdf', ['type' => 'cash-flow', 'id' => $report->id]) }}"
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
            <i class="bi bi-cash-stack fs-1 text-muted"></i>
            <h5 class="text-muted mt-3">Belum ada laporan arus kas</h5>
            @if($search || $year || $status)
            <p class="text-muted">Tidak ada hasil untuk filter yang dipilih</p>
            <a href="{{ route('financial.cash-flow.index') }}" class="btn btn-outline-primary">
                Lihat Semua Laporan
            </a>
            @else
            <p class="text-muted">Mulai buat laporan arus kas pertama Anda</p>
            <a href="{{ route('financial.cash-flow.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Buat Laporan Pertama
            </a>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.open(`${window.location.pathname}?${params.toString()}`, '_blank');
}
</script>
@endpush
