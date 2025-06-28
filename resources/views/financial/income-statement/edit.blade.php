{{-- resources/views/financial/income-statement/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Edit Laporan Perhitungan Hasil Usaha')

@php
$header = [
'title' => 'Edit Laporan Perhitungan Hasil Usaha Tahun ' . $report->reporting_year,
'subtitle' => 'Perbarui laporan laba rugi koperasi'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Laporan Perhitungan Hasil Usaha', 'url' => route('financial.income-statement.index')],
['title' => 'Tahun ' . $report->reporting_year, 'url' => route('financial.income-statement.show', $report)],
['title' => 'Edit', 'url' => route('financial.income-statement.edit', $report)]
];
@endphp

@section('content')
<form action="{{ route('financial.income-statement.update', $report) }}" method="POST" id="incomeStatementForm"
    x-data="incomeStatementForm()">
    @csrf
    @method('PUT')

    <!-- Report Header -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-info-circle me-2"></i>
                Informasi Laporan
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="reporting_year" class="form-label">Tahun Pelaporan</label>
                    <input type="text" class="form-control bg-light" value="{{ $report->reporting_year }}" readonly>
                    <input type="hidden" name="reporting_year" value="{{ $report->reporting_year }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="report_date" class="form-label">Tanggal Laporan <span
                            class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('report_date') is-invalid @enderror" id="report_date"
                        name="report_date" value="{{ old('report_date', $report->report_date->format('Y-m-d')) }}"
                        required>
                    @error('report_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 mb-3">
                    <label for="notes" class="form-label">Catatan</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes"
                        rows="3">{{ old('notes', $report->notes) }}</textarea>
                    @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <!-- PENDAPATAN -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                <i class="bi bi-arrow-up-circle me-2"></i>
                PENDAPATAN
            </h5>
        </div>
        <div class="card-body">
            <!-- Pendapatan Operasional -->
            <h6 class="text-success border-bottom pb-2 mb-3">Pendapatan Operasional</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="pendapatan_jasa_simpan_pinjam" class="form-label">Pendapatan Jasa Simpan Pinjam</label>
                    <input type="number"
                        class="form-control money-input @error('pendapatan_jasa_simpan_pinjam') is-invalid @enderror"
                        id="pendapatan_jasa_simpan_pinjam" name="pendapatan_jasa_simpan_pinjam"
                        value="{{ old('pendapatan_jasa_simpan_pinjam', $accounts['revenue']['operational']['pendapatan_jasa_simpan_pinjam'] ?? 0) }}"
                        x-model="data.pendapatan_jasa_simpan_pinjam" @input="calculateTotals()">
                    @error('pendapatan_jasa_simpan_pinjam')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="pendapatan_administrasi" class="form-label">Pendapatan Administrasi</label>
                    <input type="number"
                        class="form-control money-input @error('pendapatan_administrasi') is-invalid @enderror"
                        id="pendapatan_administrasi" name="pendapatan_administrasi"
                        value="{{ old('pendapatan_administrasi', $accounts['revenue']['operational']['pendapatan_administrasi'] ?? 0) }}"
                        x-model="data.pendapatan_administrasi" @input="calculateTotals()">
                    @error('pendapatan_administrasi')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="pendapatan_provisi" class="form-label">Pendapatan Provisi</label>
                    <input type="number"
                        class="form-control money-input @error('pendapatan_provisi') is-invalid @enderror"
                        id="pendapatan_provisi" name="pendapatan_provisi"
                        value="{{ old('pendapatan_provisi', $accounts['revenue']['operational']['pendapatan_provisi'] ?? 0) }}"
                        x-model="data.pendapatan_provisi" @input="calculateTotals()">
                    @error('pendapatan_provisi')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="pendapatan_operasional_lainnya" class="form-label">Pendapatan Operasional
                        Lainnya</label>
                    <input type="number"
                        class="form-control money-input @error('pendapatan_operasional_lainnya') is-invalid @enderror"
                        id="pendapatan_operasional_lainnya" name="pendapatan_operasional_lainnya"
                        value="{{ old('pendapatan_operasional_lainnya', $accounts['revenue']['operational']['pendapatan_operasional_lainnya'] ?? 0) }}"
                        x-model="data.pendapatan_operasional_lainnya" @input="calculateTotals()">
                    @error('pendapatan_operasional_lainnya')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Pendapatan Operasional</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_pendapatan_operasional)" readonly>
                    <input type="hidden" name="total_pendapatan_operasional"
                        x-bind:value="data.total_pendapatan_operasional">
                </div>
            </div>

            <!-- Pendapatan Non-Operasional -->
            <h6 class="text-success border-bottom pb-2 mb-3 mt-4">Pendapatan Non-Operasional</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="pendapatan_bunga_bank" class="form-label">Pendapatan Bunga Bank</label>
                    <input type="number"
                        class="form-control money-input @error('pendapatan_bunga_bank') is-invalid @enderror"
                        id="pendapatan_bunga_bank" name="pendapatan_bunga_bank"
                        value="{{ old('pendapatan_bunga_bank', $accounts['revenue']['non_operational']['pendapatan_bunga_bank'] ?? 0) }}"
                        x-model="data.pendapatan_bunga_bank" @input="calculateTotals()">
                    @error('pendapatan_bunga_bank')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="pendapatan_non_operasional_lainnya" class="form-label">Pendapatan Non-Operasional
                        Lainnya</label>
                    <input type="number"
                        class="form-control money-input @error('pendapatan_non_operasional_lainnya') is-invalid @enderror"
                        id="pendapatan_non_operasional_lainnya" name="pendapatan_non_operasional_lainnya"
                        value="{{ old('pendapatan_non_operasional_lainnya', $accounts['revenue']['non_operational']['pendapatan_non_operasional_lainnya'] ?? 0) }}"
                        x-model="data.pendapatan_non_operasional_lainnya" @input="calculateTotals()">
                    @error('pendapatan_non_operasional_lainnya')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Pendapatan Non-Operasional</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_pendapatan_non_operasional)" readonly>
                    <input type="hidden" name="total_pendapatan_non_operasional"
                        x-bind:value="data.total_pendapatan_non_operasional">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold text-success fs-5">TOTAL PENDAPATAN</label>
                    <input type="text" class="form-control bg-success text-white fw-bold fs-5"
                        x-bind:value="formatCurrency(data.total_pendapatan)" readonly>
                    <input type="hidden" name="total_pendapatan" x-bind:value="data.total_pendapatan">
                </div>
            </div>
        </div>
    </div>

    <!-- BEBAN -->
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">
                <i class="bi bi-arrow-down-circle me-2"></i>
                BEBAN
            </h5>
        </div>
        <div class="card-body">
            <!-- Beban Operasional -->
            <h6 class="text-danger border-bottom pb-2 mb-3">Beban Operasional</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="beban_bunga_simpanan" class="form-label">Beban Bunga Simpanan</label>
                    <input type="number"
                        class="form-control money-input @error('beban_bunga_simpanan') is-invalid @enderror"
                        id="beban_bunga_simpanan" name="beban_bunga_simpanan"
                        value="{{ old('beban_bunga_simpanan', $accounts['expenses']['operational']['beban_bunga_simpanan'] ?? 0) }}"
                        x-model="data.beban_bunga_simpanan" @input="calculateTotals()">
                    @error('beban_bunga_simpanan')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="beban_administrasi_umum" class="form-label">Beban Administrasi & Umum</label>
                    <input type="number"
                        class="form-control money-input @error('beban_administrasi_umum') is-invalid @enderror"
                        id="beban_administrasi_umum" name="beban_administrasi_umum"
                        value="{{ old('beban_administrasi_umum', $accounts['expenses']['operational']['beban_administrasi_umum'] ?? 0) }}"
                        x-model="data.beban_administrasi_umum" @input="calculateTotals()">
                    @error('beban_administrasi_umum')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="beban_personalia" class="form-label">Beban Personalia</label>
                    <input type="number"
                        class="form-control money-input @error('beban_personalia') is-invalid @enderror"
                        id="beban_personalia" name="beban_personalia"
                        value="{{ old('beban_personalia', $accounts['expenses']['operational']['beban_personalia'] ?? 0) }}"
                        x-model="data.beban_personalia" @input="calculateTotals()">
                    @error('beban_personalia')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="beban_penyusutan" class="form-label">Beban Penyusutan</label>
                    <input type="number"
                        class="form-control money-input @error('beban_penyusutan') is-invalid @enderror"
                        id="beban_penyusutan" name="beban_penyusutan"
                        value="{{ old('beban_penyusutan', $accounts['expenses']['operational']['beban_penyusutan'] ?? 0) }}"
                        x-model="data.beban_penyusutan" @input="calculateTotals()">
                    @error('beban_penyusutan')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="beban_operasional_lainnya" class="form-label">Beban Operasional Lainnya</label>
                    <input type="number"
                        class="form-control money-input @error('beban_operasional_lainnya') is-invalid @enderror"
                        id="beban_operasional_lainnya" name="beban_operasional_lainnya"
                        value="{{ old('beban_operasional_lainnya', $accounts['expenses']['operational']['beban_operasional_lainnya'] ?? 0) }}"
                        x-model="data.beban_operasional_lainnya" @input="calculateTotals()">
                    @error('beban_operasional_lainnya')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Beban Operasional</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_beban_operasional)" readonly>
                    <input type="hidden" name="total_beban_operasional" x-bind:value="data.total_beban_operasional">
                </div>
            </div>

            <!-- Beban Non-Operasional -->
            <h6 class="text-danger border-bottom pb-2 mb-3 mt-4">Beban Non-Operasional</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="beban_bunga_bank" class="form-label">Beban Bunga Bank</label>
                    <input type="number"
                        class="form-control money-input @error('beban_bunga_bank') is-invalid @enderror"
                        id="beban_bunga_bank" name="beban_bunga_bank"
                        value="{{ old('beban_bunga_bank', $accounts['expenses']['non_operational']['beban_bunga_bank'] ?? 0) }}"
                        x-model="data.beban_bunga_bank" @input="calculateTotals()">
                    @error('beban_bunga_bank')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="beban_non_operasional_lainnya" class="form-label">Beban Non-Operasional Lainnya</label>
                    <input type="number"
                        class="form-control money-input @error('beban_non_operasional_lainnya') is-invalid @enderror"
                        id="beban_non_operasional_lainnya" name="beban_non_operasional_lainnya"
                        value="{{ old('beban_non_operasional_lainnya', $accounts['expenses']['non_operational']['beban_non_operasional_lainnya'] ?? 0) }}"
                        x-model="data.beban_non_operasional_lainnya" @input="calculateTotals()">
                    @error('beban_non_operasional_lainnya')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Beban Non-Operasional</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_beban_non_operasional)" readonly>
                    <input type="hidden" name="total_beban_non_operasional"
                        x-bind:value="data.total_beban_non_operasional">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold text-danger fs-5">TOTAL BEBAN</label>
                    <input type="text" class="form-control bg-danger text-white fw-bold fs-5"
                        x-bind:value="formatCurrency(data.total_beban)" readonly>
                    <input type="hidden" name="total_beban" x-bind:value="data.total_beban">
                </div>
            </div>
        </div>
    </div>

    <!-- LABA/RUGI -->
    <div class="card mb-4">
        <div class="card-header" x-bind:class="netIncome >= 0 ? 'bg-success text-white' : 'bg-danger text-white'">
            <h5 class="mb-0">
                <i class="bi bi-calculator me-2"></i>
                LABA/RUGI BERSIH
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Total Pendapatan</label>
                    <input type="text" class="form-control bg-success text-white fw-bold"
                        x-bind:value="formatCurrency(data.total_pendapatan)" readonly>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Total Beban</label>
                    <input type="text" class="form-control bg-danger text-white fw-bold"
                        x-bind:value="formatCurrency(data.total_beban)" readonly>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold fs-5"
                        x-text="netIncome >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH'"></label>
                    <input type="text" class="form-control fw-bold fs-5"
                        x-bind:class="netIncome >= 0 ? 'bg-success text-white' : 'bg-danger text-white'"
                        x-bind:value="formatCurrency(Math.abs(netIncome))" readonly>
                    <input type="hidden" name="laba_rugi_bersih" x-bind:value="netIncome">
                </div>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <a href="{{ route('financial.income-statement.show', $report) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
                <div>
                    <button type="button" class="btn btn-outline-info me-2" onclick="autoSave()">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Simpan Otomatis
                    </button>
                    <button type="submit" name="action" value="draft" class="btn btn-outline-warning me-2">
                        <i class="bi bi-file-earmark me-1"></i> Simpan Draft
                    </button>
                    <button type="submit" name="action" value="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i> Simpan & Kirim
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    function incomeStatementForm() {
    return {
        data: {
            // Initialize with existing data
            pendapatan_jasa_simpan_pinjam: {{ $accounts['revenue']['operational']['pendapatan_jasa_simpan_pinjam'] ?? 0 }},
            pendapatan_administrasi: {{ $accounts['revenue']['operational']['pendapatan_administrasi'] ?? 0 }},
            pendapatan_provisi: {{ $accounts['revenue']['operational']['pendapatan_provisi'] ?? 0 }},
            pendapatan_operasional_lainnya: {{ $accounts['revenue']['operational']['pendapatan_operasional_lainnya'] ?? 0 }},
            total_pendapatan_operasional: {{ $totals['total_pendapatan_operasional'] ?? 0 }},

            pendapatan_bunga_bank: {{ $accounts['revenue']['non_operational']['pendapatan_bunga_bank'] ?? 0 }},
            pendapatan_non_operasional_lainnya: {{ $accounts['revenue']['non_operational']['pendapatan_non_operasional_lainnya'] ?? 0 }},
            total_pendapatan_non_operasional: {{ $totals['total_pendapatan_non_operasional'] ?? 0 }},
            total_pendapatan: {{ $totals['total_pendapatan'] ?? 0 }},

            beban_bunga_simpanan: {{ $accounts['expenses']['operational']['beban_bunga_simpanan'] ?? 0 }},
            beban_administrasi_umum: {{ $accounts['expenses']['operational']['beban_administrasi_umum'] ?? 0 }},
            beban_personalia: {{ $accounts['expenses']['operational']['beban_personalia'] ?? 0 }},
            beban_penyusutan: {{ $accounts['expenses']['operational']['beban_penyusutan'] ?? 0 }},
            beban_operasional_lainnya: {{ $accounts['expenses']['operational']['beban_operasional_lainnya'] ?? 0 }},
            total_beban_operasional: {{ $totals['total_beban_operasional'] ?? 0 }},

            beban_bunga_bank: {{ $accounts['expenses']['non_operational']['beban_bunga_bank'] ?? 0 }},
            beban_non_operasional_lainnya: {{ $accounts['expenses']['non_operational']['beban_non_operasional_lainnya'] ?? 0 }},
            total_beban_non_operasional: {{ $totals['total_beban_non_operasional'] ?? 0 }},
            total_beban: {{ $totals['total_beban'] ?? 0 }}
        },

        get netIncome() {
            return this.data.total_pendapatan - this.data.total_beban;
        },

        calculateTotals() {
            // Calculate Pendapatan Operasional
            this.data.total_pendapatan_operasional =
                parseFloat(this.data.pendapatan_jasa_simpan_pinjam || 0) +
                parseFloat(this.data.pendapatan_administrasi || 0) +
                parseFloat(this.data.pendapatan_provisi || 0) +
                parseFloat(this.data.pendapatan_operasional_lainnya || 0);

            // Calculate Pendapatan Non-Operasional
            this.data.total_pendapatan_non_operasional =
                parseFloat(this.data.pendapatan_bunga_bank || 0) +
                parseFloat(this.data.pendapatan_non_operasional_lainnya || 0);

            // Calculate Total Pendapatan
            this.data.total_pendapatan = this.data.total_pendapatan_operasional + this.data.total_pendapatan_non_operasional;

            // Calculate Beban Operasional
            this.data.total_beban_operasional =
                parseFloat(this.data.beban_bunga_simpanan || 0) +
                parseFloat(this.data.beban_administrasi_umum || 0) +
                parseFloat(this.data.beban_personalia || 0) +
                parseFloat(this.data.beban_penyusutan || 0) +
                parseFloat(this.data.beban_operasional_lainnya || 0);

            // Calculate Beban Non-Operasional
            this.data.total_beban_non_operasional =
                parseFloat(this.data.beban_bunga_bank || 0) +
                parseFloat(this.data.beban_non_operasional_lainnya || 0);

            // Calculate Total Beban
            this.data.total_beban = this.data.total_beban_operasional + this.data.total_beban_non_operasional;
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount || 0);
        },

        init() {
            this.calculateTotals();
        }
    }
}

function autoSave() {
    const formData = new FormData(document.getElementById('incomeStatementForm'));

    fetch('/api/financial/auto-save/income-statement/{{ $report->id }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': SIMKOP.csrfToken
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            SIMKOP.showToast('Data berhasil disimpan otomatis', 'success');
        }
    })
    .catch(error => {
        console.error('Auto-save error:', error);
    });
}

// Auto-save every 2 minutes
setInterval(autoSave, 120000);
</script>
@endpush
