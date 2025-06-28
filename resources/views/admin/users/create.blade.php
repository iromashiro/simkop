{{-- resources/views/admin/users/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Tambah Pengguna')

@php
$header = [
'title' => 'Tambah Pengguna Baru',
'subtitle' => 'Buat akun pengguna baru untuk sistem SIMKOP'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Kelola Pengguna', 'url' => route('admin.users.index')],
['title' => 'Tambah Pengguna', 'url' => route('admin.users.create')]
];
@endphp

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-person-plus me-2"></i>
                    Formulir Pengguna Baru
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.users.store') }}" method="POST">
                    @csrf

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
                                name="name" value="{{ old('name') }}" required>
                            @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                                name="email" value="{{ old('email') }}" required>
                            @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Email akan digunakan untuk login</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Nomor Telepon</label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone"
                                name="phone" value="{{ old('phone') }}">
                            @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control @error('password') is-invalid @enderror"
                                    id="password" name="password" required>
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePassword('password')">
                                    <i class="bi bi-eye" id="password-icon"></i>
                                </button>
                            </div>
                            @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Minimal 8 karakter</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="password_confirmation" class="form-label">Konfirmasi Password <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password_confirmation"
                                    name="password_confirmation" required>
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePassword('password_confirmation')">
                                    <i class="bi bi-eye" id="password_confirmation-icon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="address" class="form-label">Alamat</label>
                            <textarea class="form-control @error('address') is-invalid @enderror" id="address"
                                name="address" rows="3">{{ old('address') }}</textarea>
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
                                    {{ old('role') === $roleOption->name ? 'selected' : '' }}>
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
                                    {{ old('cooperative_id') == $cooperativeOption->id ? 'selected' : '' }}>
                                    {{ $cooperativeOption->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('cooperative_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Kosongkan jika role Admin Dinas</div>
                        </div>
                    </div>

                    <!-- Additional Settings -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-gear me-1"></i>
                                Pengaturan Tambahan
                            </h6>
                        </div>

                        <div class="col-12 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="must_change_password"
                                    name="must_change_password" value="1"
                                    {{ old('must_change_password', '1') ? 'checked' : '' }}>
                                <label class="form-check-label" for="must_change_password">
                                    Wajib ganti password saat login pertama
                                </label>
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="send_welcome_email"
                                    name="send_welcome_email" value="1"
                                    {{ old('send_welcome_email', '1') ? 'checked' : '' }}>
                                <label class="form-check-label" for="send_welcome_email">
                                    Kirim email selamat datang
                                </label>
                            </div>
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
                                    <button type="reset" class="btn btn-outline-warning me-2">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Reset
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-1"></i> Buat Pengguna
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

// Trigger role change on page load
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('role').dispatchEvent(new Event('change'));
});
</script>
@endpush
