{{-- resources/views/admin/cooperatives/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Edit Koperasi')

@php
$header = [
'title' => 'Edit Koperasi',
'subtitle' => 'Perbarui informasi koperasi ' . $cooperative->name
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Kelola Koperasi', 'url' => route('admin.cooperatives.index')],
['title' => $cooperative->name, 'url' => route('admin.cooperatives.show', $cooperative)],
['title' => 'Edit', 'url' => route('admin.cooperatives.edit', $cooperative)]
];
@endphp

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-pencil me-2"></i>
                    Edit Koperasi: {{ $cooperative->name }}
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.cooperatives.update', $cooperative) }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

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
                                name="name" value="{{ old('name', $cooperative->name) }}" required>
                            @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="code" class="form-label">Kode Koperasi</label>
                            <input type="text" class="form-control @error('code') is-invalid @enderror" id="code"
                                name="code" value="{{ old('code', $cooperative->code) }}" readonly>
                            @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Kode tidak dapat diubah</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="registration_number" class="form-label">Nomor Registrasi <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('registration_number') is-invalid @enderror"
                                id="registration_number" name="registration_number"
                                value="{{ old('registration_number', $cooperative->registration_number) }}" required>
                            @error('registration_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="registration_date" class="form-label">Tanggal Registrasi <span
                                    class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('registration_date') is-invalid @enderror"
                                id="registration_date" name="registration_date"
                                value="{{ old('registration_date', $cooperative->registration_date->format('Y-m-d')) }}"
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
                                name="address" rows="3" required>{{ old('address', $cooperative->address) }}</textarea>
                            @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Nomor Telepon</label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone"
                                name="phone" value="{{ old('phone', $cooperative->phone) }}">
                            @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                                name="email" value="{{ old('email', $cooperative->email) }}">
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
                                id="chairman_name" name="chairman_name"
                                value="{{ old('chairman_name', $cooperative->chairman_name) }}">
                            @error('chairman_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="chairman_phone" class="form-label">Telepon Ketua</label>
                            <input type="text" class="form-control @error('chairman_phone') is-invalid @enderror"
                                id="chairman_phone" name="chairman_phone"
                                value="{{ old('chairman_phone', $cooperative->chairman_phone) }}">
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
                                <option value="simpan_pinjam"
                                    {{ old('type', $cooperative->type) == 'simpan_pinjam' ? 'selected' : '' }}>Simpan
                                    Pinjam</option>
                                <option value="konsumen"
                                    {{ old('type', $cooperative->type) == 'konsumen' ? 'selected' : '' }}>Konsumen
                                </option>
                                <option value="produsen"
                                    {{ old('type', $cooperative->type) == 'produsen' ? 'selected' : '' }}>Produsen
                                </option>
                                <option value="jasa" {{ old('type', $cooperative->type) == 'jasa' ? 'selected' : '' }}>
                                    Jasa</option>
                            </select>
                            @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                                <option value="active"
                                    {{ old('status', $cooperative->status) == 'active' ? 'selected' : '' }}>Aktif
                                </option>
                                <option value="inactive"
                                    {{ old('status', $cooperative->status) == 'inactive' ? 'selected' : '' }}>Tidak
                                    Aktif</option>
                                <option value="pending"
                                    {{ old('status', $cooperative->status) == 'pending' ? 'selected' : '' }}>Pending
                                </option>
                            </select>
                            @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 mb-3">
                            <label for="description" class="form-label">Deskripsi</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description"
                                name="description"
                                rows="3">{{ old('description', $cooperative->description) }}</textarea>
                            @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('admin.cooperatives.show', $cooperative) }}"
                                    class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Kembali
                                </a>
                                <div>
                                    <button type="reset" class="btn btn-outline-warning me-2">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Reset
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
