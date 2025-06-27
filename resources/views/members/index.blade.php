@extends('layouts.app')

@section('title', 'Manajemen Anggota')

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-people me-2"></i>
    Manajemen Anggota
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    @can('create_members')
    <div class="btn-group me-2">
        <a href="{{ route('members.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>
            Tambah Anggota
        </a>
    </div>
    @endcan
    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i>
            Export
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="{{ route('members.export', ['format' => 'excel']) }}">
                    <i class="bi bi-file-earmark-excel me-2"></i>Excel
                </a></li>
            <li><a class="dropdown-item" href="{{ route('members.export', ['format' => 'pdf']) }}">
                    <i class="bi bi-file-earmark-pdf me-2"></i>PDF
                </a></li>
        </ul>
    </div>
</div>
@endsection

@section('content')
<div x-data="memberManagement()" x-init="init()">
    <!-- Statistics Cards -->
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
                                {{ number_format($statistics['total_members'] ?? 0) }}
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
                                Anggota Aktif
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($statistics['active_members'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-person-check text-success" style="font-size: 2rem;"></i>
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
                                {{ number_format($statistics['pending_members'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock text-warning" style="font-size: 2rem;"></i>
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
                                Anggota Baru (Bulan Ini)
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($statistics['new_members_this_month'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-person-plus text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter & Pencarian</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('members.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" id="search" name="search"
                            value="{{ request('search') }}" placeholder="Cari nama, nomor anggota, atau telepon...">
                    </div>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Menunggu</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Tidak Aktif
                        </option>
                        <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>Ditangguhkan
                        </option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="join_date_from" class="form-label">Tanggal Bergabung</label>
                    <input type="date" class="form-control" id="join_date_from" name="join_date_from"
                        value="{{ request('join_date_from') }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i>
                            Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Members Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Anggota</h6>
            <div class="d-flex align-items-center">
                <span class="text-muted me-3">
                    Menampilkan {{ $members->firstItem() ?? 0 }} - {{ $members->lastItem() ?? 0 }}
                    dari {{ $members->total() }} anggota
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No. Anggota</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Telepon</th>
                            <th>Tanggal Bergabung</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($members as $member)
                        <tr>
                            <td>
                                <span class="fw-bold text-primary">{{ $member->member_number }}</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-3">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                            style="width: 40px; height: 40px;">
                                            {{ strtoupper(substr($member->full_name, 0, 2)) }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fw-bold">{{ $member->full_name }}</div>
                                        <small class="text-muted">ID: {{ $member->id_number }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $member->email }}</td>
                            <td>{{ $member->phone }}</td>
                            <td>{{ $member->join_date->format('d M Y') }}</td>
                            <td>
                                @switch($member->status)
                                @case('active')
                                <span class="badge bg-success">Aktif</span>
                                @break
                                @case('pending')
                                <span class="badge bg-warning">Menunggu</span>
                                @break
                                @case('inactive')
                                <span class="badge bg-secondary">Tidak Aktif</span>
                                @break
                                @case('suspended')
                                <span class="badge bg-danger">Ditangguhkan</span>
                                @break
                                @default
                                <span class="badge bg-light text-dark">{{ ucfirst($member->status) }}</span>
                                @endswitch
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    @can('view_members')
                                    <a href="{{ route('members.show', $member) }}"
                                        class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @endcan

                                    @can('edit_members')
                                    <a href="{{ route('members.edit', $member) }}"
                                        class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @endcan

                                    @can('delete_members')
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus"
                                        @click="deleteMember({{ $member->id }}, '{{ $member->full_name }}')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-inbox display-4"></i>
                                    <p class="mt-2">Tidak ada anggota ditemukan</p>
                                    @can('create_members')
                                    <a href="{{ route('members.create') }}" class="btn btn-primary">
                                        <i class="bi bi-person-plus me-1"></i>
                                        Tambah Anggota Pertama
                                    </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($members->hasPages())
        <div class="card-footer">
            {{ $members->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    function memberManagement() {
    return {
        loading: false,

        init() {
            // Initialize any required functionality
        },

        async deleteMember(memberId, memberName) {
            if (!confirm(`Apakah Anda yakin ingin menghapus anggota "${memberName}"?\n\nTindakan ini tidak dapat dibatalkan.`)) {
                return;
            }

            this.loading = true;

            try {
                const response = await fetch(`/members/${memberId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    HERMES.utils.showToast('Anggota berhasil dihapus', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    const error = await response.json();
                    HERMES.utils.showToast(error.message || 'Gagal menghapus anggota', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                HERMES.utils.showToast('Terjadi kesalahan sistem', 'error');
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endpush
@endsection
