@extends('layouts.app')

@section('title', 'Tambah Anggota Baru')

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-person-plus me-2"></i>
    Tambah Anggota Baru
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    <a href="{{ route('members.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>
        Kembali
    </a>
</div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-person-badge me-2"></i>
                    Informasi Anggota Baru
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('members.store') }}" x-data="memberForm()" @submit="handleSubmit"
                    novalidate>
                    @csrf

                    <!-- Personal Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-person me-2"></i>
                                Informasi Pribadi
                            </h6>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">
                                Nama Lengkap <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control @error('full_name') is-invalid @enderror"
                                id="full_name" name="full_name" value="{{ old('full_name') }}" required
                                x-model="form.full_name">
                            @error('full_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="id_number" class="form-label">
                                Nomor KTP <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control @error('id_number') is-invalid @enderror"
                                id="id_number" name="id_number" value="{{ old('id_number') }}" required maxlength="16"
                                x-model="form.id_number">
                            @error('id_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="birth_date" class="form-label">Tanggal Lahir</label>
                            <input type="date" class="form-control @error('birth_date') is-invalid @enderror"
                                id="birth_date" name="birth_date" value="{{ old('birth_date') }}"
                                x-model="form.birth_date">
                            @error('birth_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="join_date" class="form-label">
                                Tanggal Bergabung <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control @error('join_date') is-invalid @enderror"
                                id="join_date" name="join_date" value="{{ old('join_date', date('Y-m-d')) }}" required
                                x-model="form.join_date">
                            @error('join_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 mb-3">
                            <label for="address" class="form-label">
                                Alamat <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control @error('address') is-invalid @enderror" id="address"
                                name="address" rows="3" required x-model="form.address">{{ old('address') }}</textarea>
                            @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-telephone me-2"></i>
                                Informasi Kontak
                            </h6>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">
                                Email <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                                name="email" value="{{ old('email') }}" required x-model="form.email">
                            @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">
                                Nomor Telepon <span class="text-danger">*</span>
                            </label>
                            <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone"
                                name="phone" value="{{ old('phone') }}" required x-model="form.phone">
                            @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Financial Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-cash-coin me-2"></i>
                                Informasi Keuangan
                            </h6>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="initial_deposit" class="form-label">Setoran Awal</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control @error('initial_deposit') is-invalid @enderror"
                                    id="initial_deposit" name="initial_deposit" value="{{ old('initial_deposit', 0) }}"
                                    min="0" step="1000" x-model="form.initial_deposit">
                            </div>
                            @error('initial_deposit')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Setoran awal minimal sesuai ketentuan koperasi
                            </small>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('members.index') }}" class="btn btn-secondary">
                                    <i class="bi bi-x-circle me-1"></i>
                                    Batal
                                </a>
                                <button type="submit" class="btn btn-primary" :disabled="loading">
                                    <span x-show="!loading">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Simpan Anggota
                                    </span>
                                    <span x-show="loading">
                                        <span class="spinner-border spinner-border-sm me-2"></span>
                                        Menyimpan...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function memberForm() {
    return {
        form: {
            full_name: '',
            id_number: '',
            birth_date: '',
            join_date: '{{ date('Y-m-d') }}',
            address: '',
            email: '',
            phone: '',
            initial_deposit: 0
        },
        loading: false,

        handleSubmit(event) {
            this.loading = true;
            // Form will submit normally, loading state will show until page reloads
        }
    }
}
</script>
@endpush
@endsection
