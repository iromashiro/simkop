{{-- resources/views/financial/balance-sheet/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Edit Neraca')

@php
$header = [
'title' => 'Edit Neraca Tahun ' . $report->reporting_year,
'subtitle' => 'Perbarui laporan posisi keuangan koperasi'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Neraca', 'url' => route('financial.balance-sheet.index')],
['title' => 'Tahun ' . $report->reporting_year, 'url' => route('financial.balance-sheet.show',
$report->reporting_year)],
['title' => 'Edit', 'url' => route('financial.balance-sheet.edit', $report->reporting_year)]
];
@endphp

@section('content')
<form action="{{ route('financial.balance-sheet.update', $report->reporting_year) }}" method="POST"
    id="balanceSheetForm" x-data="balanceSheetForm()">
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

    <!-- ASET -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="bi bi-building me-2"></i>
                ASET
            </h5>
        </div>
        <div class="card-body">
            <!-- Aset Lancar -->
            <h6 class="text-primary border-bottom pb-2 mb-3">Aset Lancar</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="kas_bank" class="form-label">Kas dan Bank</label>
                    <input type="number" class="form-control money-input @error('kas_bank') is-invalid @enderror"
                        id="kas_bank" name="kas_bank" value="{{ old('kas_bank', $report->kas_bank ?? 0) }}"
                        x-model="data.kas_bank" @input="calculateTotals()">
                    @error('kas_bank')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="piutang_anggota" class="form-label">Piutang Anggota</label>
                    <input type="number" class="form-control money-input @error('piutang_anggota') is-invalid @enderror"
                        id="piutang_anggota" name="piutang_anggota"
                        value="{{ old('piutang_anggota', $report->piutang_anggota ?? 0) }}"
                        x-model="data.piutang_anggota" @input="calculateTotals()">
                    @error('piutang_anggota')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="piutang_non_anggota" class="form-label">Piutang Non Anggota</label>
                    <input type="number"
                        class="form-control money-input @error('piutang_non_anggota') is-invalid @enderror"
                        id="piutang_non_anggota" name="piutang_non_anggota"
                        value="{{ old('piutang_non_anggota', $report->piutang_non_anggota ?? 0) }}"
                        x-model="data.piutang_non_anggota" @input="calculateTotals()">
                    @error('piutang_non_anggota')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="persediaan" class="form-label">Persediaan</label>
                    <input type="number" class="form-control money-input @error('persediaan') is-invalid @enderror"
                        id="persediaan" name="persediaan" value="{{ old('persediaan', $report->persediaan ?? 0) }}"
                        x-model="data.persediaan" @input="calculateTotals()">
                    @error('persediaan')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="aset_lancar_lainnya" class="form-label">Aset Lancar Lainnya</label>
                    <input type="number"
                        class="form-control money-input @error('aset_lancar_lainnya') is-invalid @enderror"
                        id="aset_lancar_lainnya" name="aset_lancar_lainnya"
                        value="{{ old('aset_lancar_lainnya', $report->aset_lancar_lainnya ?? 0) }}"
                        x-model="data.aset_lancar_lainnya" @input="calculateTotals()">
                    @error('aset_lancar_lainnya')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Aset Lancar</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_aset_lancar)" readonly>
                    <input type="hidden" name="total_aset_lancar" x-bind:value="data.total_aset_lancar">
                </div>
            </div>

            <!-- Aset Tidak Lancar -->
            <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Aset Tidak Lancar</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="investasi_jangka_panjang" class="form-label">Investasi Jangka Panjang</label>
                    <input type="number"
                        class="form-control money-input @error('investasi_jangka_panjang') is-invalid @enderror"
                        id="investasi_jangka_panjang" name="investasi_jangka_panjang"
                        value="{{ old('investasi_jangka_panjang', $report->investasi_jangka_panjang ?? 0) }}"
                        x-model="data.investasi_jangka_panjang" @input="calculateTotals()">
                    @error('investasi_jangka_panjang')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="aset_tetap" class="form-label">Aset Tetap</label>
                    <input type="number" class="form-control money-input @error('aset_tetap') is-invalid @enderror"
                        id="aset_tetap" name="aset_tetap" value="{{ old('aset_tetap', $report->aset_tetap ?? 0) }}"
                        x-model="data.aset_tetap" @input="calculateTotals()">
                    @error('aset_tetap')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="akumulasi_penyusutan" class="form-label">Akumulasi Penyusutan</label>
                    <input type="number"
                        class="form-control money-input @error('akumulasi_penyusutan') is-invalid @enderror"
                        id="akumulasi_penyusutan" name="akumulasi_penyusutan"
                        value="{{ old('akumulasi_penyusutan', $report->akumulasi_penyusutan ?? 0) }}"
                        x-model="data.akumulasi_penyusutan" @input="calculateTotals()">
                    @error('akumulasi_penyusutan')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="aset_tidak_lancar_lainnya" class="form-label">Aset Tidak Lancar Lainnya</label>
                    <input type="number"
                        class="form-control money-input @error('aset_tidak_lancar_lainnya') is-invalid @enderror"
                        id="aset_tidak_lancar_lainnya" name="aset_tidak_lancar_lainnya"
                        value="{{ old('aset_tidak_lancar_lainnya', $report->aset_tidak_lancar_lainnya ?? 0) }}"
                        x-model="data.aset_tidak_lancar_lainnya" @input="calculateTotals()">
                    @error('aset_tidak_lancar_lainnya')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Aset Tidak Lancar</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_aset_tidak_lancar)" readonly>
                    <input type="hidden" name="total_aset_tidak_lancar" x-bind:value="data.total_aset_tidak_lancar">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold text-primary fs-5">TOTAL ASET</label>
                    <input type="text" class="form-control bg-primary text-white fw-bold fs-5"
                        x-bind:value="formatCurrency(data.total_aset)" readonly>
                    <input type="hidden" name="total_aset" x-bind:value="data.total_aset">
                </div>
            </div>
        </div>
    </div>

    <!-- KEWAJIBAN -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>
                KEWAJIBAN
            </h5>
        </div>
        <div class="card-body">
            <!-- Kewajiban Jangka Pendek -->
            <h6 class="text-warning border-bottom pb-2 mb-3">Kewajiban Jangka Pendek</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="simpanan_anggota" class="form-label">Simpanan Anggota</label>
                    <input type="number"
                        class="form-control money-input @error('simpanan_anggota') is-invalid @enderror"
                        id="simpanan_anggota" name="simpanan_anggota"
                        value="{{ old('simpanan_anggota', $report->simpanan_anggota ?? 0) }}"
                        x-model="data.simpanan_anggota" @input="calculateTotals()">
                    @error('simpanan_anggota')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="simpanan_non_anggota" class="form-label">Simpanan Non Anggota</label>
                    <input type="number"
                        class="form-control money-input @error('simpanan_non_anggota') is-invalid @enderror"
                        id="simpanan_non_anggota" name="simpanan_non_anggota"
                        value="{{ old('simpanan_non_anggota', $report->simpanan_non_anggota ?? 0) }}"
                        x-model="data.simpanan_non_anggota" @input="calculateTotals()">
                    @error('simpanan_non_anggota')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="hutang_bank" class="form-label">Hutang Bank</label>
                    <input type="number" class="form-control money-input @error('hutang_bank') is-invalid @enderror"
                        id="hutang_bank" name="hutang_bank" value="{{ old('hutang_bank', $report->hutang_bank ?? 0) }}"
                        x-model="data.hutang_bank" @input="calculateTotals()">
                    @error('hutang_bank')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="hutang_lainnya" class="form-label">Hutang Lainnya</label>
                    <input type="number" class="form-control money-input @error('hutang_lainnya') is-invalid @enderror"
                        id="hutang_lainnya" name="hutang_lainnya"
                        value="{{ old('hutang_lainnya', $report->hutang_lainnya ?? 0) }}" x-model="data.hutang_lainnya"
                        @input="calculateTotals()">
                    @error('hutang_lainnya')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Kewajiban Jangka Pendek</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_kewajiban_jangka_pendek)" readonly>
                    <input type="hidden" name="total_kewajiban_jangka_pendek"
                        x-bind:value="data.total_kewajiban_jangka_pendek">
                </div>
            </div>

            <!-- Kewajiban Jangka Panjang -->
            <h6 class="text-warning border-bottom pb-2 mb-3 mt-4">Kewajiban Jangka Panjang</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="hutang_jangka_panjang" class="form-label">Hutang Jangka Panjang</label>
                    <input type="number"
                        class="form-control money-input @error('hutang_jangka_panjang') is-invalid @enderror"
                        id="hutang_jangka_panjang" name="hutang_jangka_panjang"
                        value="{{ old('hutang_jangka_panjang', $report->hutang_jangka_panjang ?? 0) }}"
                        x-model="data.hutang_jangka_panjang" @input="calculateTotals()">
                    @error('hutang_jangka_panjang')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="kewajiban_lainnya" class="form-label">Kewajiban Lainnya</label>
                    <input type="number"
                        class="form-control money-input @error('kewajiban_lainnya') is-invalid @enderror"
                        id="kewajiban_lainnya" name="kewajiban_lainnya"
                        value="{{ old('kewajiban_lainnya', $report->kewajiban_lainnya ?? 0) }}"
                        x-model="data.kewajiban_lainnya" @input="calculateTotals()">
                    @error('kewajiban_lainnya')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Kewajiban Jangka Panjang</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_kewajiban_jangka_panjang)" readonly>
                    <input type="hidden" name="total_kewajiban_jangka_panjang"
                        x-bind:value="data.total_kewajiban_jangka_panjang">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold text-warning fs-5">TOTAL KEWAJIBAN</label>
                    <input type="text" class="form-control bg-warning text-dark fw-bold fs-5"
                        x-bind:value="formatCurrency(data.total_kewajiban)" readonly>
                    <input type="hidden" name="total_kewajiban" x-bind:value="data.total_kewajiban">
                </div>
            </div>
        </div>
    </div>

    <!-- EKUITAS -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                <i class="bi bi-pie-chart me-2"></i>
                EKUITAS
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="simpanan_pokok" class="form-label">Simpanan Pokok</label>
                    <input type="number" class="form-control money-input @error('simpanan_pokok') is-invalid @enderror"
                        id="simpanan_pokok" name="simpanan_pokok"
                        value="{{ old('simpanan_pokok', $report->simpanan_pokok ?? 0) }}" x-model="data.simpanan_pokok"
                        @input="calculateTotals()">
                    @error('simpanan_pokok')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="simpanan_wajib" class="form-label">Simpanan Wajib</label>
                    <input type="number" class="form-control money-input @error('simpanan_wajib') is-invalid @enderror"
                        id="simpanan_wajib" name="simpanan_wajib"
                        value="{{ old('simpanan_wajib', $report->simpanan_wajib ?? 0) }}" x-model="data.simpanan_wajib"
                        @input="calculateTotals()">
                    @error('simpanan_wajib')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="cadangan" class="form-label">Cadangan</label>
                    <input type="number" class="form-control money-input @error('cadangan') is-invalid @enderror"
                        id="cadangan" name="cadangan" value="{{ old('cadangan', $report->cadangan ?? 0) }}"
                        x-model="data.cadangan" @input="calculateTotals()">
                    @error('cadangan')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="shu_belum_dibagi" class="form-label">SHU Belum Dibagi</label>
                    <input type="number"
                        class="form-control money-input @error('shu_belum_dibagi') is-invalid @enderror"
                        id="shu_belum_dibagi" name="shu_belum_dibagi"
                        value="{{ old('shu_belum_dibagi', $report->shu_belum_dibagi ?? 0) }}"
                        x-model="data.shu_belum_dibagi" @input="calculateTotals()">
                    @error('shu_belum_dibagi')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="ekuitas_lainnya" class="form-label">Ekuitas Lainnya</label>
                    <input type="number" class="form-control money-input @error('ekuitas_lainnya') is-invalid @enderror"
                        id="ekuitas_lainnya" name="ekuitas_lainnya"
                        value="{{ old('ekuitas_lainnya', $report->ekuitas_lainnya ?? 0) }}"
                        x-model="data.ekuitas_lainnya" @input="calculateTotals()">
                    @error('ekuitas_lainnya')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold text-success fs-5">TOTAL EKUITAS</label>
                    <input type="text" class="form-control bg-success text-white fw-bold fs-5"
                        x-bind:value="formatCurrency(data.total_ekuitas)" readonly>
                    <input type="hidden" name="total_ekuitas" x-bind:value="data.total_ekuitas">
                </div>
            </div>
        </div>
    </div>

    <!-- Balance Check -->
    <div class="card mb-4" x-show="!isBalanced" x-transition>
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Peringatan: Neraca Tidak Seimbang
            </h5>
        </div>
        <div class="card-body">
            <div class="alert alert-danger">
                <strong>Total Aset tidak sama dengan Total Kewajiban + Ekuitas</strong><br>
                Selisih: <span x-text="formatCurrency(Math.abs(balanceDifference))"></span><br>
                Silakan periksa kembali input data Anda.
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <a href="{{ route('financial.balance-sheet.show', $report->reporting_year) }}"
                    class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
                <div>
                    <button type="button" class="btn btn-outline-info me-2" onclick="autoSave()">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Simpan Otomatis
                    </button>
                    <button type="submit" name="action" value="draft" class="btn btn-outline-warning me-2">
                        <i class="bi bi-file-earmark me-1"></i> Simpan Draft
                    </button>
                    <button type="submit" name="action" value="submit" class="btn btn-primary"
                        x-bind:disabled="!isBalanced">
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
    function balanceSheetForm() {
    return {
        data: {
            // Initialize with existing data
            kas_bank: {{ $report->kas_bank ?? 0 }},
            piutang_anggota: {{ $report->piutang_anggota ?? 0 }},
            piutang_non_anggota: {{ $report->piutang_non_anggota ?? 0 }},
            persediaan: {{ $report->persediaan ?? 0 }},
            aset_lancar_lainnya: {{ $report->aset_lancar_lainnya ?? 0 }},
            total_aset_lancar: {{ $report->total_aset_lancar ?? 0 }},

            investasi_jangka_panjang: {{ $report->investasi_jangka_panjang ?? 0 }},
            aset_tetap: {{ $report->aset_tetap ?? 0 }},
            akumulasi_penyusutan: {{ $report->akumulasi_penyusutan ?? 0 }},
            aset_tidak_lancar_lainnya: {{ $report->aset_tidak_lancar_lainnya ?? 0 }},
            total_aset_tidak_lancar: {{ $report->total_aset_tidak_lancar ?? 0 }},
            total_aset: {{ $report->total_aset ?? 0 }},

            simpanan_anggota: {{ $report->simpanan_anggota ?? 0 }},
            simpanan_non_anggota: {{ $report->simpanan_non_anggota ?? 0 }},
            hutang_bank: {{ $report->hutang_bank ?? 0 }},
            hutang_lainnya: {{ $report->hutang_lainnya ?? 0 }},
            total_kewajiban_jangka_pendek: {{ $report->total_kewajiban_jangka_pendek ?? 0 }},

            hutang_jangka_panjang: {{ $report->hutang_jangka_panjang ?? 0 }},
            kewajiban_lainnya: {{ $report->kewajiban_lainnya ?? 0 }},
            total_kewajiban_jangka_panjang: {{ $report->total_kewajiban_jangka_panjang ?? 0 }},
            total_kewajiban: {{ $report->total_kewajiban ?? 0 }},

            simpanan_pokok: {{ $report->simpanan_pokok ?? 0 }},
            simpanan_wajib: {{ $report->simpanan_wajib ?? 0 }},
            cadangan: {{ $report->cadangan ?? 0 }},
            shu_belum_dibagi: {{ $report->shu_belum_dibagi ?? 0 }},
            ekuitas_lainnya: {{ $report->ekuitas_lainnya ?? 0 }},
            total_ekuitas: {{ $report->total_ekuitas ?? 0 }}
        },

        get isBalanced() {
            return Math.abs(this.balanceDifference) < 1;
        },

        get balanceDifference() {
            return this.data.total_aset - (this.data.total_kewajiban + this.data.total_ekuitas);
        },

        calculateTotals() {
            // Calculate Aset Lancar
            this.data.total_aset_lancar =
                parseFloat(this.data.kas_bank || 0) +
                parseFloat(this.data.piutang_anggota || 0) +
                parseFloat(this.data.piutang_non_anggota || 0) +
                parseFloat(this.data.persediaan || 0) +
                parseFloat(this.data.aset_lancar_lainnya || 0);

            // Calculate Aset Tidak Lancar
            this.data.total_aset_tidak_lancar =
                parseFloat(this.data.investasi_jangka_panjang || 0) +
                parseFloat(this.data.aset_tetap || 0) -
                parseFloat(this.data.akumulasi_penyusutan || 0) +
                parseFloat(this.data.aset_tidak_lancar_lainnya || 0);

            // Calculate Total Aset
            this.data.total_aset = this.data.total_aset_lancar + this.data.total_aset_tidak_lancar;

            // Calculate Kewajiban Jangka Pendek
            this.data.total_kewajiban_jangka_pendek =
                parseFloat(this.data.simpanan_anggota || 0) +
                parseFloat(this.data.simpanan_non_anggota || 0) +
                parseFloat(this.data.hutang_bank || 0) +
                parseFloat(this.data.hutang_lainnya || 0);

            // Calculate Kewajiban Jangka Panjang
            this.data.total_kewajiban_jangka_panjang =
                parseFloat(this.data.hutang_jangka_panjang || 0) +
                parseFloat(this.data.kewajiban_lainnya || 0);

            // Calculate Total Kewajiban
            this.data.total_kewajiban = this.data.total_kewajiban_jangka_pendek + this.data.total_kewajiban_jangka_panjang;

            // Calculate Total Ekuitas
            this.data.total_ekuitas =
                parseFloat(this.data.simpanan_pokok || 0) +
                parseFloat(this.data.simpanan_wajib || 0) +
                parseFloat(this.data.cadangan || 0) +
                parseFloat(this.data.shu_belum_dibagi || 0) +
                parseFloat(this.data.ekuitas_lainnya || 0);
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
    const formData = new FormData(document.getElementById('balanceSheetForm'));

    fetch('/api/financial/auto-save/balance-sheet/{{ $report->reporting_year }}', {
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
