@extends('layouts.app')

@section('title', 'Buka Rekening Simpanan Baru')

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-plus-circle me-2"></i>
    Buka Rekening Simpanan Baru
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    <a href="{{ route('savings.index') }}" class="btn btn-outline-secondary">
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
                    <i class="bi bi-bank me-2"></i>
                    Informasi Rekening Baru
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('savings.store') }}" x-data="savingsForm()" @submit="handleSubmit"
                    novalidate>
                    @csrf

                    <!-- Member Selection -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-person me-2"></i>
                                Pilih Anggota
                            </h6>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="member_id" class="form-label">
                                Anggota <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('member_id') is-invalid @enderror" id="member_id"
                                name="member_id" required x-model="form.member_id">
                                <option value="">Pilih Anggota</option>
                                @foreach($members as $member)
                                <option value="{{ $member->id }}"
                                    {{ old('member_id') == $member->id ? 'selected' : '' }}>
                                    {{ $member->full_name }} - {{ $member->member_number }}
                                </option>
                                @endforeach
                            </select>
                            @error('member_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Savings Product Selection -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-box me-2"></i>
                                Produk Simpanan
                            </h6>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="savings_product_id" class="form-label">
                                Produk Simpanan <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('savings_product_id') is-invalid @enderror"
                                id="savings_product_id" name="savings_product_id" required
                                x-model="form.savings_product_id" @change="updateProductInfo">
                                <option value="">Pilih Produk Simpanan</option>
                                @foreach($savingsProducts as $product)
                                <option value="{{ $product->id }}" data-interest-rate="{{ $product->interest_rate }}"
                                    data-min-balance="{{ $product->minimum_balance }}"
                                    {{ old('savings_product_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('savings_product_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Product Info Display -->
                        <div class="col-12" x-show="selectedProduct">
                            <div class="alert alert-info">
                                <h6 class="alert-heading">Informasi Produk</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Tingkat Bunga:</strong>
                                        <span x-text="selectedProduct?.interest_rate || 0"></span>% per tahun
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Saldo Minimum:</strong>
                                        Rp <span x-text="formatNumber(selectedProduct?.minimum_balance || 0)"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Initial Deposit -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-cash-coin me-2"></i>
                                Setoran Awal
                            </h6>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="initial_deposit" class="form-label">
                                Setoran Awal <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control @error('initial_deposit') is-invalid @enderror"
                                    id="initial_deposit" name="initial_deposit" value="{{ old('initial_deposit', 0) }}"
                                    min="0" step="1000" required x-model="form.initial_deposit">
                            </div>
                            @error('initial_deposit')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Setoran awal minimal sesuai dengan produk simpanan yang dipilih
                            </small>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('savings.index') }}" class="btn btn-secondary">
                                    <i class="bi bi-x-circle me-1"></i>
                                    Batal
                                </a>
                                <button type="submit" class="btn btn-primary" :disabled="loading">
                                    <span x-show="!loading">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Buka Rekening
                                    </span>
                                    <span x-show="loading">
                                        <span class="spinner-border spinner-border-sm me-2"></span>
                                        Memproses...
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
    function savingsForm() {
    return {
        form: {
            member_id: '',
            savings_product_id: '',
            initial_deposit: 0
        },
        loading: false,
        selectedProduct: null,

        handleSubmit(event) {
            this.loading = true;
            // Form will submit normally, loading state will show until page reloads
        },

        updateProductInfo() {
            const select = document.getElementById('savings_product_id');
            const selectedOption = select.options[select.selectedIndex];

            if (selectedOption.value) {
                this.selectedProduct = {
                    interest_rate: selectedOption.dataset.interestRate,
                    minimum_balance: selectedOption.dataset.minBalance
                };
            } else {
                this.selectedProduct = null;
            }
        },

        formatNumber(number) {
            return new Intl.NumberFormat('id-ID').format(number);
        }
    }
}
</script>
@endpush
@endsection
