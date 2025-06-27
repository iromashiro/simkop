@extends('layouts.app')

@section('title', 'Pengajuan Pinjaman Baru')

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-plus-circle me-2"></i>
    Pengajuan Pinjaman Baru
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>
        Kembali
    </a>
</div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-cash-stack me-2"></i>
                    Formulir Pengajuan Pinjaman
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('loans.store') }}" x-data="loanForm()" @submit="handleSubmit"
                    novalidate>
                    @csrf

                    <!-- Member Selection -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-person me-2"></i>
                                Informasi Peminjam
                            </h6>
                        </div>

                        <div class="col-md-6 mb-3">
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

                        <div class="col-md-6 mb-3">
                            <label for="loan_product_id" class="form-label">
                                Produk Pinjaman <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('loan_product_id') is-invalid @enderror"
                                id="loan_product_id" name="loan_product_id" required x-model="form.loan_product_id"
                                @change="updateProductInfo">
                                <option value="">Pilih Produk Pinjaman</option>
                                @foreach($loanProducts as $product)
                                <option value="{{ $product->id }}" data-interest-rate="{{ $product->interest_rate }}"
                                    data-max-amount="{{ $product->maximum_amount }}"
                                    data-max-term="{{ $product->maximum_term_months }}"
                                    {{ old('loan_product_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('loan_product_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Product Info Display -->
                        <div class="col-12" x-show="selectedProduct">
                            <div class="alert alert-info">
                                <h6 class="alert-heading">Informasi Produk</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>Tingkat Bunga:</strong>
                                        <span x-text="selectedProduct?.interest_rate || 0"></span>% per tahun
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Maksimal Pinjaman:</strong>
                                        Rp <span x-text="formatNumber(selectedProduct?.max_amount || 0)"></span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Maksimal Jangka Waktu:</strong>
                                        <span x-text="selectedProduct?.max_term || 0"></span> bulan
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loan Details -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-calculator me-2"></i>
                                Detail Pinjaman
                            </h6>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="principal_amount" class="form-label">
                                Jumlah Pinjaman <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number"
                                    class="form-control @error('principal_amount') is-invalid @enderror"
                                    id="principal_amount" name="principal_amount" value="{{ old('principal_amount') }}"
                                    min="100000" step="50000" required x-model="form.principal_amount"
                                    @input="calculateInstallment">
                            </div>
                            @error('principal_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="interest_rate" class="form-label">
                                Tingkat Bunga (% per tahun) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control @error('interest_rate') is-invalid @enderror"
                                    id="interest_rate" name="interest_rate" value="{{ old('interest_rate') }}" min="0"
                                    step="0.01" required x-model="form.interest_rate" @input="calculateInstallment">
                                <span class="input-group-text">%</span>
                            </div>
                            @error('interest_rate')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="term_months" class="form-label">
                                Jangka Waktu (bulan) <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control @error('term_months') is-invalid @enderror"
                                id="term_months" name="term_months" value="{{ old('term_months') }}" min="1" max="60"
                                required x-model="form.term_months" @input="calculateInstallment">
                            @error('term_months')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="disbursement_date" class="form-label">
                                Tanggal Pencairan <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control @error('disbursement_date') is-invalid @enderror"
                                id="disbursement_date" name="disbursement_date"
                                value="{{ old('disbursement_date', date('Y-m-d')) }}" required
                                x-model="form.disbursement_date">
                            @error('disbursement_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="purpose" class="form-label">
                                Tujuan Pinjaman <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control @error('purpose') is-invalid @enderror" id="purpose"
                                name="purpose" value="{{ old('purpose') }}" required x-model="form.purpose"
                                placeholder="Contoh: Modal usaha, renovasi rumah, dll">
                            @error('purpose')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Loan Calculation Preview -->
                        <div class="col-12" x-show="monthlyInstallment > 0">
                            <div class="alert alert-success">
                                <h6 class="alert-heading">Simulasi Angsuran</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Jumlah Pinjaman:</strong><br>
                                        Rp <span x-text="formatNumber(form.principal_amount)"></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Angsuran per Bulan:</strong><br>
                                        <span class="text-success fw-bold">
                                            Rp <span x-text="formatNumber(monthlyInstallment)"></span>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Total Pembayaran:</strong><br>
                                        Rp <span x-text="formatNumber(totalPayment)"></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Total Bunga:</strong><br>
                                        Rp <span x-text="formatNumber(totalInterest)"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('loans.index') }}" class="btn btn-secondary">
                                    <i class="bi bi-x-circle me-1"></i>
                                    Batal
                                </a>
                                <button type="submit" class="btn btn-primary" :disabled="loading">
                                    <span x-show="!loading">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Ajukan Pinjaman
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
    function loanForm() {
    return {
        form: {
            member_id: '',
            loan_product_id: '',
            principal_amount: 0,
            interest_rate: 0,
            term_months: 0,
            disbursement_date: '{{ date('Y-m-d') }}',
            purpose: ''
        },
        loading: false,
        selectedProduct: null,
        monthlyInstallment: 0,
        totalPayment: 0,
        totalInterest: 0,

        handleSubmit(event) {
            this.loading = true;
            // Form will submit normally, loading state will show until page reloads
        },

        updateProductInfo() {
            const select = document.getElementById('loan_product_id');
            const selectedOption = select.options[select.selectedIndex];

            if (selectedOption.value) {
                this.selectedProduct = {
                    interest_rate: selectedOption.dataset.interestRate,
                    max_amount: selectedOption.dataset.maxAmount,
                    max_term: selectedOption.dataset.maxTerm
                };

                // Auto-fill interest rate
                this.form.interest_rate = parseFloat(selectedOption.dataset.interestRate);
                this.calculateInstallment();
            } else {
                this.selectedProduct = null;
            }
        },

        calculateInstallment() {
            const principal = parseFloat(this.form.principal_amount) || 0;
            const rate = parseFloat(this.form.interest_rate) || 0;
            const term = parseInt(this.form.term_months) || 0;

            if (principal > 0 && rate > 0 && term > 0) {
                const monthlyRate = rate / 100 / 12;
                const installment = principal * (monthlyRate * Math.pow(1 + monthlyRate, term)) / (Math.pow(1 + monthlyRate, term) - 1);

                this.monthlyInstallment = Math.round(installment);
                this.totalPayment = this.monthlyInstallment * term;
                this.totalInterest = this.totalPayment - principal;
            } else {
                this.monthlyInstallment = 0;
                this.totalPayment = 0;
                this.totalInterest = 0;
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
