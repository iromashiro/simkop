{{-- resources/views/admin/users/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Edit Pengguna')

@php
$header = [
'title' => 'Edit Pengguna',
'subtitle' => 'Perbarui informasi pengguna ' . $user->name
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Kelola Pengguna', 'url' => route('admin.users.index')],
['title' => $user->name, 'url' => '#'],
['title' => 'Edit', 'url' => route('admin.users.edit', $user)]
];
@endphp

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-pencil me-2"></i>
                    Edit Pengguna: {{ $user->name }}
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.users.update', $user) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <!-- Personal Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-person me-1"></i>
                                Informasi Personal
                            </h6>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                                name="name" value="{{ old('name', $user->name) }}" required>
                            @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                                name="email" value="{{ old('email', $user->email) }}" required>
                            @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Nomor Telepon</label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone"
                                name="phone" value="{{ old('phone', $user->phone) }}">
                            @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password Baru</label>
                            <div class="input-group">
                                <input type="password" class="form-control @error('password') is-invalid @enderror"
                                    id="password" name="password">
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePassword('password')">
                                    <i class="bi bi-eye" id="password-icon"></i>
                                </button>
                            </div>
                            @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Kosongkan jika tidak ingin mengubah password</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password_confirmation"
                                    name="password_confirmation">
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePassword('password_confirmation')">
                                    <i class="bi bi-eye" id="password_confirmation-icon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="address" class="form-label">Alamat</label>
                            <textarea class="form-control @error('address') is-invalid @enderror" id="address"
                                name="address" rows="3">{{ old('address', $user->address) }}</textarea>
                            @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Role & Access -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-shield-check me-1"></i>
                                Role & Akses
                            </h6>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select @error('role') is-invalid @enderror" id="role" name="role"
                                required>
                                <option value="">Pilih Role</option>
                                @foreach($roles as $roleOption)
                                <option value="{{ $roleOption->name }}"
                                    {{ old('role', $user->roles->first()->name ?? '') === $roleOption->name ? 'selected' : '' }}>
                                    {{ ucwords(str_replace('_', ' ', $roleOption->name)) }}
                                </option>
                                @endforeach
                            </select>
                            @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="cooperative_id" class="form-label">Koperasi</label>
                            <select class="form-select @error('cooperative_id') is-invalid @enderror"
                                id="cooperative_id" name="cooperative_id">
                                <option value="">Pilih Koperasi</option>
                                @foreach($cooperatives as $cooperativeOption)
                                <option value="{{ $cooperativeOption->id }}"
                                    {{ old('cooperative_id', $user->cooperative_id) == $cooperativeOption->id ? 'selected' : '' }}>
                                    {{ $cooperativeOption->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('cooperative_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Account Status -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                Status Akun
                            </h6>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Verification</label>
                            <div>
                                @if($user->email_verified_at)
                                <span class="badge bg-success fs-6">
                                    <i class="bi bi-check-circle me-1"></i>
                                    Terverifikasi pada {{ $user->email_verified_at->format('d/m/Y H:i') }}
                                </span>
                                @else
                                <span class="badge bg-warning fs-6">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Belum terverifikasi
                                </span>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                        onclick="sendVerificationEmail({{ $user->id }})">
                                        <i class="bi bi-envelope me-1"></i> Kirim Email Verifikasi
                                    </button>
                                </div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Terakhir Login</label>
                            <div>
                                @if($user->last_login_at)
                                <div class="fw-bold">{{ $user->last_login_at->format('d/m/Y H:i') }}</div>
                                <small class="text-muted">{{ $user->last_login_at->diffForHumans() }}</small>
                                @else
                                <span class="text-muted">Belum pernah login</span>
                                @endif
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="must_change_password"
                                    name="must_change_password" value="1"
                                    {{ old('must_change_password', $user->must_change_password) ? 'checked' : '' }}>
                                <label class="form-check-label" for="must_change_password">
                                    Wajib ganti password saat login berikutnya
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Account Info -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-clock me-1"></i>
                                Informasi Akun
                            </h6>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Dibuat</label>
                            <div class="fw-bold">{{ $user->created_at->format('d F Y H:i') }}</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Terakhir Update</label>
                            <div class="fw-bold">{{ $user->updated_at->format('d F Y H:i') }}</div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Kembali
                                </a>
                                <div>
                                    <button type="button" class="btn btn-outline-warning me-2"
                                        onclick="resetUserPassword({{ $user->id }})">
                                        <i class="bi bi-key me-1"></i> Reset Password
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-1"></i> Simpan Perubahan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');

    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

// Role-based cooperative field visibility
document.getElementById('role').addEventListener('change', function() {
    const cooperativeField = document.getElementById('cooperative_id');
    const cooperativeLabel = cooperativeField.previousElementSibling;

    if (this.value === 'admin_dinas') {
        cooperativeField.value = '';
        cooperativeField.disabled = true;
        cooperativeField.required = false;
        cooperativeLabel.innerHTML = 'Koperasi <small class="text-muted">(Tidak diperlukan untuk Admin Dinas)</small>';
    } else {
        cooperativeField.disabled = false;
        cooperativeField.required = true;
        cooperativeLabel.innerHTML = 'Koperasi <span class="text-danger">*</span>';
    }
});

// Reset password function
function resetUserPassword(userId) {
    if (confirm('Apakah Anda yakin ingin mereset password pengguna ini? Password baru akan dikirim ke email pengguna.')) {
        fetch(`/admin/users/${userId}/reset-password`, {
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
                location.reload();
            } else {
                alert('Gagal mereset password: ' + data.message);
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan: ' + error.message);
        });
    }
}

// Send verification email
function sendVerificationEmail(userId) {
    fetch(`/admin/users/${userId}/send-verification`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': SIMKOP.csrfToken,
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Email verifikasi berhasil dikirim.');
        } else {
            alert('Gagal mengirim email verifikasi: ' + data.message);
        }
    })
    .catch(error => {
        alert('Terjadi kesalahan: ' + error.message);
    });
}

// Trigger role change on page load
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('role').dispatchEvent(new Event('change'));
});
</script>
@endpush
