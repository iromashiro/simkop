{{-- resources/views/admin/users/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Kelola Pengguna')

@php
$header = [
'title' => 'Kelola Pengguna',
'subtitle' => 'Manajemen pengguna sistem SIMKOP',
'actions' => '<a href="' . route('admin.users.create') . '" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i> Tambah Pengguna
</a>'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Kelola Pengguna', 'url' => route('admin.users.index')]
];
@endphp

@section('content')
<!-- Filter & Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Cari Pengguna</label>
                <input type="text" class="form-control" name="search" value="{{ $search }}"
                    placeholder="Nama atau email pengguna...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Role</label>
                <select class="form-select" name="role">
                    <option value="">Semua Role</option>
                    @foreach($roles as $roleOption)
                    <option value="{{ $roleOption->name }}" {{ $role === $roleOption->name ? 'selected' : '' }}>
                        {{ ucwords(str_replace('_', ' ', $roleOption->name)) }}
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
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-people me-2"></i>
            Daftar Pengguna ({{ $users->total() }} pengguna)
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
        @if($users->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Koperasi</th>
                        <th>Status</th>
                        <th>Bergabung</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3"
                                    style="width: 40px; height: 40px;">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="fw-bold">{{ $user->name }}</div>
                                    <small class="text-muted">ID: {{ $user->id }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>{{ $user->email }}</div>
                            @if($user->email_verified_at)
                            <small class="text-success">
                                <i class="bi bi-check-circle"></i> Terverifikasi
                            </small>
                            @else
                            <small class="text-warning">
                                <i class="bi bi-exclamation-triangle"></i> Belum verifikasi
                            </small>
                            @endif
                        </td>
                        <td>
                            @foreach($user->roles as $userRole)
                            <span class="badge bg-info">
                                {{ ucwords(str_replace('_', ' ', $userRole->name)) }}
                            </span>
                            @endforeach
                        </td>
                        <td>
                            @if($user->cooperative)
                            <div class="fw-bold">{{ $user->cooperative->name }}</div>
                            <small class="text-muted">{{ $user->cooperative->code }}</small>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($user->email_verified_at)
                            <span class="badge bg-success">Aktif</span>
                            @else
                            <span class="badge bg-warning">Pending</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $user->created_at->format('d/m/Y') }}</div>
                            <small class="text-muted">{{ $user->created_at->diffForHumans() }}</small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-primary"
                                    title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-warning"
                                    onclick="resetPassword({{ $user->id }})" title="Reset Password">
                                    <i class="bi bi-key"></i>
                                </button>
                                @if($user->id !== auth()->id())
                                <button type="button" class="btn btn-outline-danger"
                                    onclick="deleteUser({{ $user->id }})" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
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
                Menampilkan {{ $users->firstItem() }} - {{ $users->lastItem() }}
                dari {{ $users->total() }} pengguna
            </div>
            {{ $users->appends(request()->query())->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="bi bi-people fs-1 text-muted"></i>
            <h5 class="text-muted mt-3">Tidak ada pengguna ditemukan</h5>
            @if($search || $role || $cooperative)
            <p class="text-muted">Tidak ada hasil untuk filter yang dipilih</p>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary">
                Lihat Semua Pengguna
            </a>
            @else
            <p class="text-muted">Belum ada pengguna yang terdaftar</p>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Tambah Pengguna Pertama
            </a>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    function deleteUser(id) {
    if (SIMKOP.confirmDelete('Apakah Anda yakin ingin menghapus pengguna ini? Data yang terkait juga akan terhapus.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/admin/users/${id}`;
        form.innerHTML = `
            <input type="hidden" name="_token" value="${SIMKOP.csrfToken}">
            <input type="hidden" name="_method" value="DELETE">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function resetPassword(id) {
    if (confirm('Apakah Anda yakin ingin mereset password pengguna ini? Password baru akan dikirim ke email pengguna.')) {
        fetch(`/admin/users/${id}/reset-password`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': SIMKOP.csrfToken,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Password berhasil direset. Password baru telah dikirim ke email pengguna.');
            } else {
                alert('Gagal mereset password: ' + data.message);
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan: ' + error.message);
        });
    }
}

function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.open(`${window.location.pathname}?${params.toString()}`, '_blank');
}
</script>
@endpush
