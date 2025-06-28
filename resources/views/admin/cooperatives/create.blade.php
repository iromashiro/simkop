{{-- resources/views/admin/cooperatives/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Tambah Koperasi')

@php
$header = [
'title' => 'Tambah Koperasi Baru',
'subtitle' => 'Daftarkan koperasi baru ke dalam sistem'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Kelola Koperasi', 'url' => route('admin.cooperatives.index')],
['title' => 'Tambah Koperasi', 'url' => route('admin.cooperatives.create')]
];
@endphp

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-building me-2"></i>
                    Formulir Koperasi Baru
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cooperatives.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <!-- Basic Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                Informasi Dasar
                            </h6>
                        </div>

                        <div class="col-md-8 mb-3">
                            <label for="name" class="form-label">Nama Koperasi <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                                name="name" value="{{ old('name') }}" required>
                            @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Nama lengkap koperasi sesuai akta pendirian</div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="code" class="form-label">Kode Koperasi</label>
                            <input type="text" class="form-control @error('code') is-invalid @enderror" id="code"
                                name="code" value="{{ old('code') }}" readonly>
                            @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Kode akan dibuat otomatis</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="registration_number" class="form-label">Nomor Registrasi <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('registration_number') is-invalid @enderror"
                                id="registration_number" name="registration_number"
                                value="{{ old('registration_number') }}" required>
                            @error('registration_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="registration_date" class="form-label">Tanggal Registrasi <span
                                    class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('registration_date') is-invalid @enderror"
                                id="registration_date" name="registration_date" value="{{ old('registration_date') }}"
                                required>
                            @error('registration_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-telephone me-1"></i>
                                Informasi Kontak
                            </h6>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="address" class="form-label">Alamat Lengkap <span
                                    class="text-danger">*</span></label>
                            <textarea class="form-control @error('address') is-invalid @enderror" id="address"
                                name="address" rows="3" required>{{ old('address') }}</textarea>
                            @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
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
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                                name="email" value="{{ old('email') }}">
                            @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Leadership Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-person-badge me-1"></i>
                                Informasi Pengurus
                            </h6>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="chairman_name" class="form-label">Nama Ketua</label>
                            <input type="text" class="form-control @error('chairman_name') is-invalid @enderror"
                                id="chairman_name" name="chairman_name" value="{{ old('chairman_name') }}">
                            @error('chairman_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="chairman_phone" class="form-label">Telepon Ketua</label>
                            <input type="text" class="form-control @error('chairman_phone') is-invalid @enderror"
                                id="chairman_phone" name="chairman_phone" value="{{ old('chairman_phone') }}">
                            @error('chairman_phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-gear me-1"></i>
                                Informasi Tambahan
                            </h6>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label">Jenis Koperasi</label>
                            <select class="form-select @error('type') is-invalid @enderror" id="type" name="type">
                                <option value="">Pilih Jenis Koperasi</option>
                                <option value="simpan_pinjam" {{ old('type') == 'simpan_pinjam' ? 'selected' : '' }}>
                                    Simpan Pinjam</option>
                                <option value="konsumen" {{ old('type') == 'konsumen' ? 'selected' : '' }}>Konsumen
                                </option>
                                <option value="produsen" {{ old('type') == 'produsen' ? 'selected' : '' }}>Produsen
                                </option>
                                <option value="jasa" {{ old('type') == 'jasa' ? 'selected' : '' }}>Jasa</option>
                            </select>
                            @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                                <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Aktif
                                </option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Tidak Aktif
                                </option>
                                <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>Pending
                                </option>
                            </select>
                            @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 mb-3">
                            <label for="description" class="form-label">Deskripsi</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description"
                                name="description" rows="3">{{ old('description') }}</textarea>
                            @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Deskripsi singkat tentang koperasi</div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('admin.cooperatives.index') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Kembali
                                </a>
                                <div>
                                    <button type="reset" class="btn btn-outline-warning me-2">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Reset
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-1"></i> Simpan Koperasi
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
    // Auto-generate code from name
document.getElementById('name').addEventListener('input', function() {
    const name = this.value;
    const codeField = document.getElementById('code');

    if (name.length >= 3) {
        // Extract first 3 characters and make uppercase
        const prefix = name.replace(/[^a-zA-Z0-9\s]/g, '').substring(0, 3).toUpperCase();
        const suffix = Math.floor(Math.random() * 900) + 100; // Random 3-digit number
        codeField.value = prefix + suffix;
    } else {
        codeField.value = '';
    }
});
</script>
@endpush
