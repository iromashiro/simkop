@extends('layouts.app')

@section('title', 'Manajemen Pengguna')

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-person-gear me-2"></i>
    Manajemen Pengguna
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    @can('create_users')
    <div class="btn-group me-2">
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>
            Tambah Pengguna
        </a>
    </div>
    @endcan
    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i>
            Export
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#">
                    <i class="bi bi-file-earmark-excel me-2"></i>Excel
                </a></li>
            <li><a class="dropdown-item" href="#">
                    <i class="bi bi-file-earmark-pdf me-2"></i>PDF
                </a></li>
        </ul>
    </div>
</div>
@endsection

@section('content')
<div x-data="userManagement()" x-init="init()">
    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter & Pencarian</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('users.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" id="search" name="search"
                            value="{{ request('search') }}" placeholder="Cari nama atau email...">
                    </div>
                </div>

                <div class="col-md-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">Semua Role</option>
                        <option value="super_admin" {{ request('role') == 'super_admin' ? 'selected' : '' }}>Super Admin
                        </option>
                        <option value="admin" {{ request('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                        <option value="manager" {{ request('role') == 'manager' ? 'selected' : '' }}>Manager</option>
                        <option value="staff" {{ request('role') == 'staff' ? 'selected' : '' }}>Staff</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Tidak Aktif
                        </option>
                    </select>
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

    <!-- Users Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Pengguna</h6>
            <div class="d-flex align-items-center">
                <span class="text-muted me-3">
                    Menampilkan {{ $users->firstItem() ?? 0 }} - {{ $users->lastItem() ?? 0 }}
                    dari {{ $users->total() }} pengguna
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Pengguna</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Koperasi</th>
                            <th>Status</th>
                            <th>Terakhir Login</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-3">
                                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
                                            class="rounded-circle" width="40" height="40">
                                    </div>
                                    <div>
                                        <div class="fw-bold">{{ $user->name }}</div>
                                        <small class="text-muted">ID: {{ $user->id }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @foreach($user->roles as $role)
                                <span class="badge bg-primary me-1">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if($user->primaryCooperative())
                                {{ $user->primaryCooperative()->name }}
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($user->is_active)
                                <span class="badge bg-success">Aktif</span>
                                @else
                                <span class="badge bg-secondary">Tidak Aktif</span>
                                @endif
                            </td>
                            <td>
                                @if($user->last_login_at)
                                {{ $user->last_login_at->diffForHumans() }}
                                @else
                                <span class="text-muted">Belum pernah</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    @can('view_users')
                                    <a href="{{ route('users.show', $user) }}" class="btn btn-sm btn-outline-primary"
                                        title="Lihat Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @endcan

                                    @can('edit_users')
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-warning"
                                        title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @endcan

                                    @can('delete_users')
                                    @if($user->id !== auth()->id())
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus"
                                        @click="deleteUser({{ $user->id }}, '{{ $user->name }}')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-inbox display-4"></i>
                                    <p class="mt-2">Tidak ada pengguna ditemukan</p>
                                    @can('create_users')
                                    <a href="{{ route('users.create') }}" class="btn btn-primary">
                                        <i class="bi bi-person-plus me-1"></i>
                                        Tambah Pengguna Pertama
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

        @if($users->hasPages())
        <div class="card-footer">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    function userManagement() {
    return {
        loading: false,

        init() {
            // Initialize any required functionality
        },

        async deleteUser(userId, userName) {
            if (!confirm(`Apakah Anda yakin ingin menghapus pengguna "${userName}"?\n\nTindakan ini tidak dapat dibatalkan.`)) {
                return;
            }

            this.loading = true;

            try {
                const response = await fetch(`/users/${userId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    HERMES.utils.showToast('Pengguna berhasil dihapus', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    const error = await response.json();
                    HERMES.utils.showToast(error.message || 'Gagal menghapus pengguna', 'error');
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
