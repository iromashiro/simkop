@extends('layouts.app')

@section('title', 'Setoran Simpanan - ' . $savingsAccount->account_number)

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-plus-circle me-2"></i>
    Setoran Simpanan
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    <a href="{{ route('savings.show', $savingsAccount) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>
        Kembali
    </a>
</div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Account Info -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-1">{{ $savingsAccount->account_number }}</h5>
                        <p class="text-muted mb-0">{{ $savingsAccount->member->full_name }}</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <h4 class="text-success mb-0">
                            Rp {{ number_format($savingsAccount->balance, 0, ',', '.') }}
                        </h4>
                        <small class="text-muted">Saldo Saat Ini</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deposit Form -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-cash-coin me-2"></i>
                    Form Setoran
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('savings.process-deposit', $savingsAccount) }}"
                    x-data="depositForm()" @submit="handleSubmit" novalidate>
                    @csrf

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">
                                Jumlah Setoran <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control @error('amount') is-invalid @enderror"
                                    id="amount" name="amount" value="{{ old('amount') }}" min="1000" step="1000"
                                    required x-model="form.amount" @input="calculateNewBalance">
                            </div>
                            @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Minimal setoran Rp 1.000
                            </small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="transaction_date" class="form-label">
                                Tanggal Transaksi <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local"
                                class="form-control @error('transaction_date') is-invalid @enderror"
                                id="transaction_date" name="transaction_date"
                                value="{{ old('transaction_date', now()->format('Y-m-d\TH:i')) }}" required
                                x-model="form.transaction_date">
                            @error('transaction_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 mb-3">
                            <label for="description" class="form-label">Keterangan</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description"
                                name="description" rows="3"
                                x-model="form.description">{{ old('description') }}</textarea>
                            @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- New Balance Preview -->
                        <div class="col-12 mb-4" x-show="form.amount > 0">
                            <div class="alert alert-info">
                                <h6 class="alert-heading">Pratinjau Transaksi</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>Saldo Saat Ini:</strong><br>
                                        Rp {{ number_format($savingsAccount->balance, 0, ',', '.') }}
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Jumlah Setoran:</strong><br>
                                        <span class="text-success">+ Rp <span
                                                x-text="formatNumber(form.amount)"></span></span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Saldo Setelah Setoran:</strong><br>
                                        <span class="text-success fw-bold">
                                            Rp <span x-text="formatNumber(newBalance)"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('savings.show', $savingsAccount) }}" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i>
                            Batal
                        </a>
                        <button type="submit" class="btn btn-success" :disabled="loading || form.amount <= 0">
                            <span x-show="!loading">
                                <i class="bi bi-check-circle me-1"></i>
                                Proses Setoran
                            </span>
                            <span x-show="loading">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Memproses...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function depositForm() {
    return {
        form: {
            amount: 0,
            transaction_date: '{{ now()->format('Y-m-d\TH:i') }}',
            description: ''
        },
        loading: false,
        currentBalance: {{ $savingsAccount->balance }},
        newBalance: {{ $savingsAccount->balance }},

        handleSubmit(event) {
            this.loading = true;
            // Form will submit normally, loading state will show until page reloads
        },

        calculateNewBalance() {
            this.newBalance = this.currentBalance + parseFloat(this.form.amount || 0);
        },

        formatNumber(number) {
            return new Intl.NumberFormat('id-ID').format(number);
        }
    }
}
</script>
@endpush
@endsection
