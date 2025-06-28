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
            <div class="col-md-3">
                <label class="form-label">Cari Koperasi</label>
                <input type="text" class="form-control" name="search" value="{{ request('search') }}"
                    placeholder="Nama atau nomor registrasi...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Tidak Aktif
                    </option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Jenis</label>
                <select class="form-select" name="type">
                    <option value="">Semua Jenis</option>
                    <option value="simpan_pinjam" {{ request('type') === 'simpan_pinjam' ? 'selected' : '' }}>Simpan
                        Pinjam</option>
                    <option value="konsumen" {{ request('type') === 'konsumen' ? 'selected' : '' }}>Konsumen</option>
                    <option value="produsen" {{ request('type') === 'produsen' ? 'selected' : '' }}>Produsen</option>
                    <option value="jasa" {{ request('type') === 'jasa' ? 'selected' : '' }}>Jasa</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Kecamatan</label>
                <select class="form-select" name="district">
                    <option value="">Semua Kecamatan</option>
                    @foreach($districts as $district)
                    <option value="{{ $district }}" {{ request('district') === $district ? 'selected' : '' }}>
                        {{ $district }}
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
                    <a href="{{ route('admin.cooperatives.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i>
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
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}"
                                class="text-decoration-none text-dark">
                                Nama Koperasi
                                @if(request('sort') === 'name')
                                <i class="bi bi-arrow-{{ request('direction') === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </a>
                        </th>
                        <th>No. Registrasi</th>
                        <th>Jenis</th>
                        <th>Alamat</th>
                        <th>Status</th>
                        <th>Anggota</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cooperatives as $cooperative)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                @if($cooperative->logo)
                                <img src="{{ Storage::url($cooperative->logo) }}" class="rounded me-3" width="40"
                                    height="40" alt="Logo">
                                @else
                                <div class="bg-primary text-white rounded d-flex align-items-center justify-content-center me-3"
                                    style="width: 40px; height: 40px;">
                                    {{ strtoupper(substr($cooperative->name, 0, 2)) }}
                                </div>
                                @endif
                                <div>
                                    <div class="fw-bold">{{ $cooperative->name }}</div>
                                    <small class="text-muted">{{ $cooperative->chairman_name }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="font-monospace">{{ $cooperative->registration_number }}</span>
                            <br>
                            <small class="text-muted">{{ $cooperative->registration_date->format('d/m/Y') }}</small>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                {{ ucwords(str_replace('_', ' ', $cooperative->type)) }}
                            </span>
                        </td>
                        <td>
                            <div>{{ $cooperative->address }}</div>
                            <small class="text-muted">{{ $cooperative->district }}, {{ $cooperative->city }}</small>
                        </td>
                        <td>
                            @switch($cooperative->status)
                            @case('active')
                            <span class="badge bg-success">Aktif</span>
                            @break
                            @case('inactive')
                            <span class="badge bg-danger">Tidak Aktif</span>
                            @break
                            @case('pending')
                            <span class="badge bg-warning">Pending</span>
                            @break
                            @endswitch
                        </td>
                        <td>
                            <div class="fw-bold">{{ $cooperative->member_count ?? 0 }}</div>
                            <small class="text-muted">anggota</small>
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
            {{ $cooperatives->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="bi bi-building fs-1 text-muted"></i>
            <h5 class="text-muted mt-3">Tidak ada koperasi ditemukan</h5>
            <p class="text-muted">Silakan ubah filter pencarian atau tambah koperasi baru</p>
            <a href="{{ route('admin.cooperatives.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Tambah Koperasi
            </a>
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
