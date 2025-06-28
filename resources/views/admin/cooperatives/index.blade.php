{{-- resources/views/admin/cooperatives/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Kelola Koperasi')

@php
$header = [
'title' => 'Kelola Koperasi',
'subtitle' => 'Manajemen data koperasi di wilayah Muara Enim',
'actions' => '<a href="' . route('admin.cooperatives.create') . '" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i> Tambah Koperasi
</a>'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Kelola Koperasi', 'url' => route('admin.cooperatives.index')]
];
@endphp

@section('content')
<!-- Filter & Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.cooperatives.index') }}" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Cari Koperasi</label>
                <input type="text" class="form-control" name="search" value="{{ $search }}"
                    placeholder="Nama, kode, atau alamat koperasi...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tampilkan per halaman</label>
                <select class="form-select" name="per_page">
                    <option value="15" {{ request('per_page') == 15 ? 'selected' : '' }}>15</option>
                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Cari
                    </button>
                    <a href="{{ route('admin.cooperatives.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Cooperatives Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-building me-2"></i>
            Daftar Koperasi ({{ $cooperatives->total() }} koperasi)
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
        @if($cooperatives->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Koperasi</th>
                        <th>Kode</th>
                        <th>Alamat</th>
                        <th>Pengguna</th>
                        <th>Laporan</th>
                        <th>Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cooperatives as $cooperative)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary text-white rounded d-flex align-items-center justify-content-center me-3"
                                    style="width: 40px; height: 40px;">
                                    {{ strtoupper(substr($cooperative->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="fw-bold">{{ $cooperative->name }}</div>
                                    <small
                                        class="text-muted">{{ $cooperative->chairman_name ?? 'Belum diatur' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="font-monospace fw-bold">{{ $cooperative->code }}</span>
                        </td>
                        <td>
                            <div>{{ Str::limit($cooperative->address, 50) }}</div>
                            <small class="text-muted">{{ $cooperative->phone ?? 'No telepon belum diatur' }}</small>
                        </td>
                        <td>
                            <div class="fw-bold">{{ $cooperative->users->count() }}</div>
                            <small class="text-muted">pengguna</small>
                        </td>
                        <td>
                            <div class="fw-bold">{{ $cooperative->financialReports->count() ?? 0 }}</div>
                            <small class="text-muted">laporan</small>
                        </td>
                        <td>
                            <div>{{ $cooperative->created_at->format('d/m/Y') }}</div>
                            <small class="text-muted">{{ $cooperative->created_at->diffForHumans() }}</small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('admin.cooperatives.show', $cooperative) }}"
                                    class="btn btn-outline-primary" title="Lihat Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admin.cooperatives.edit', $cooperative) }}"
                                    class="btn btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger"
                                    onclick="deleteCooperative({{ $cooperative->id }})" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
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
                Menampilkan {{ $cooperatives->firstItem() }} - {{ $cooperatives->lastItem() }}
                dari {{ $cooperatives->total() }} koperasi
            </div>
            {{ $cooperatives->appends(request()->query())->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="bi bi-building fs-1 text-muted"></i>
            <h5 class="text-muted mt-3">Tidak ada koperasi ditemukan</h5>
            @if($search)
            <p class="text-muted">Tidak ada hasil untuk pencarian "{{ $search }}"</p>
            <a href="{{ route('admin.cooperatives.index') }}" class="btn btn-outline-primary">
                Lihat Semua Koperasi
            </a>
            @else
            <p class="text-muted">Belum ada koperasi yang terdaftar</p>
            <a href="{{ route('admin.cooperatives.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Tambah Koperasi Pertama
            </a>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    function deleteCooperative(id) {
    if (SIMKOP.confirmDelete('Apakah Anda yakin ingin menghapus koperasi ini? Data yang terkait juga akan terhapus.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/admin/cooperatives/${id}`;
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
