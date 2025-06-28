{{-- resources/views/financial/cash-flow/create.blade.php --}}
@extends('layouts.app')

@section('title', 'Buat Laporan Arus Kas')

@php
$header = [
'title' => 'Buat Laporan Arus Kas',
'subtitle' => 'Laporan pergerakan kas masuk dan keluar koperasi'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Laporan Arus Kas', 'url' => route('financial.cash-flow.index')],
['title' => 'Buat Baru', 'url' => route('financial.cash-flow.create')]
];
@endphp

@section('content')
<form action="{{ route('financial.cash-flow.store') }}" method="POST" id="cashFlowForm" x-data="cashFlowForm()">
    @csrf

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
                    <label for="reporting_year" class="form-label">Tahun Pelaporan <span
                            class="text-danger">*</span></label>
                    <select class="form-select @error('reporting_year') is-invalid @enderror" id="reporting_year"
                        name="reporting_year" required>
                        <option value="">Pilih Tahun</option>
                        @for($year = date('Y'); $year >= date('Y') - 5; $year--)
                        <option value="{{ $year }}" {{ old('reporting_year') == $year ? 'selected' : '' }}>
                            {{ $year }}
                        </option>
                        @endfor
                    </select>
                    @error('reporting_year')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="report_date" class="form-label">Tanggal Laporan <span
                            class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('report_date') is-invalid @enderror" id="report_date"
                        name="report_date" value="{{ old('report_date', date('Y-12-31')) }}" required>
                    @error('report_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 mb-3">
                    <label for="notes" class="form-label">Catatan</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes"
                        rows="3">{{ old('notes') }}</textarea>
                    @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <!-- AKTIVITAS OPERASIONAL -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="bi bi-gear me-2"></i>
                AKTIVITAS OPERASIONAL
            </h5>
        </div>
        <div class="card-body">
            <!-- Kas Masuk Operasional -->
            <h6 class="text-primary border-bottom pb-2 mb-3">Kas Masuk dari Aktivitas Operasional</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="penerimaan_dari_anggota" class="form-label">Penerimaan dari Anggota</label>
                    <input type="number"
                        class="form-control money-input @error('penerimaan_dari_anggota') is-invalid @enderror"
                        id="penerimaan_dari_anggota" name="penerimaan_dari_anggota"
                        value="{{ old('penerimaan_dari_anggota', 0) }}" x-model="data.penerimaan_dari_anggota"
                        @input="calculateTotals()">
                    @error('penerimaan_dari_anggota')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="penerimaan_bunga_pinjaman" class="form-label">Penerimaan Bunga Pinjaman</label>
                    <input type="number"
                        class="form-control money-input @error('penerimaan_bunga_pinjaman') is-invalid @enderror"
                        id="penerimaan_bunga_pinjaman" name="penerimaan_bunga_pinjaman"
                        value="{{ old('penerimaan_bunga_pinjaman', 0) }}" x-model="data.penerimaan_bunga_pinjaman"
                        @input="calculateTotals()">
                    @error('penerimaan_bunga_pinjaman')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="penerimaan_jasa_lainnya" class="form-label">Penerimaan Jasa Lainnya</label>
                    <input type="number"
                        class="form-control money-input @error('penerimaan_jasa_lainnya') is-invalid @enderror"
                        id="penerimaan_jasa_lainnya" name="penerimaan_jasa_lainnya"
                        value="{{ old('penerimaan_jasa_lainnya', 0) }}" x-model="data.penerimaan_jasa_lainnya"
                        @input="calculateTotals()">
                    @error('penerimaan_jasa_lainnya')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Kas Masuk Operasional</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_kas_masuk_operasional)" readonly>
                    <input type="hidden" name="total_kas_masuk_operasional"
                        x-bind:value="data.total_kas_masuk_operasional">
                </div>
            </div>

            <!-- Kas Keluar Operasional -->
            <h6 class="text-primary border-bottom pb-2 mb-3 mt-4">Kas Keluar dari Aktivitas Operasional</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="pembayaran_bunga_simpanan" class="form-label">Pembayaran Bunga Simpanan</label>
                    <input type="number"
                        class="form-control money-input @error('pembayaran_bunga_simpanan') is-invalid @enderror"
                        id="pembayaran_bunga_simpanan" name="pembayaran_bunga_simpanan"
                        value="{{ old('pembayaran_bunga_simpanan', 0) }}" x-model="data.pembayaran_bunga_simpanan"
                        @input="calculateTotals()">
                    @error('pembayaran_bunga_simpanan')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="pembayaran_beban_operasional" class="form-label">Pembayaran Beban Operasional</label>
                    <input type="number"
                        class="form-control money-input @error('pembayaran_beban_operasional') is-invalid @enderror"
                        id="pembayaran_beban_operasional" name="pembayaran_beban_operasional"
                        value="{{ old('pembayaran_beban_operasional', 0) }}" x-model="data.pembayaran_beban_operasional"
                        @input="calculateTotals()">
                    @error('pembayaran_beban_operasional')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="pembayaran_gaji_karyawan" class="form-label">Pembayaran Gaji Karyawan</label>
                    <input type="number"
                        class="form-control money-input @error('pembayaran_gaji_karyawan') is-invalid @enderror"
                        id="pembayaran_gaji_karyawan" name="pembayaran_gaji_karyawan"
                        value="{{ old('pembayaran_gaji_karyawan', 0) }}" x-model="data.pembayaran_gaji_karyawan"
                        @input="calculateTotals()">
                    @error('pembayaran_gaji_karyawan')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Kas Keluar Operasional</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_kas_keluar_operasional)" readonly>
                    <input type="hidden" name="total_kas_keluar_operasional"
                        x-bind:value="data.total_kas_keluar_operasional">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold text-primary fs-5">KAS BERSIH OPERASIONAL</label>
                    <input type="text" class="form-control bg-primary text-white fw-bold fs-5"
                        x-bind:value="formatCurrency(data.kas_bersih_operasional)" readonly>
                    <input type="hidden" name="kas_bersih_operasional" x-bind:value="data.kas_bersih_operasional">
                </div>
            </div>
        </div>
    </div>

    <!-- AKTIVITAS INVESTASI -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                <i class="bi bi-graph-up me-2"></i>
                AKTIVITAS INVESTASI
            </h5>
        </div>
        <div class="card-body">
            <!-- Kas Masuk Investasi -->
            <h6 class="text-success border-bottom pb-2 mb-3">Kas Masuk dari Aktivitas Investasi</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="penjualan_aset_tetap" class="form-label">Penjualan Aset Tetap</label>
                    <input type="number"
                        class="form-control money-input @error('penjualan_aset_tetap') is-invalid @enderror"
                        id="penjualan_aset_tetap" name="penjualan_aset_tetap"
                        value="{{ old('penjualan_aset_tetap', 0) }}" x-model="data.penjualan_aset_tetap"
                        @input="calculateTotals()">
                    @error('penjualan_aset_tetap')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="penerimaan_dividen" class="form-label">Penerimaan Dividen</label>
                    <input type="number"
                        class="form-control money-input @error('penerimaan_dividen') is-invalid @enderror"
                        id="penerimaan_dividen" name="penerimaan_dividen" value="{{ old('penerimaan_dividen', 0) }}"
                        x-model="data.penerimaan_dividen" @input="calculateTotals()">
                    @error('penerimaan_dividen')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Kas Masuk Investasi</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_kas_masuk_investasi)" readonly>
                    <input type="hidden" name="total_kas_masuk_investasi" x-bind:value="data.total_kas_masuk_investasi">
                </div>
            </div>

            <!-- Kas Keluar Investasi -->
            <h6 class="text-success border-bottom pb-2 mb-3 mt-4">Kas Keluar dari Aktivitas Investasi</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="pembelian_aset_tetap" class="form-label">Pembelian Aset Tetap</label>
                    <input type="number"
                        class="form-control money-input @error('pembelian_aset_tetap') is-invalid @enderror"
                        id="pembelian_aset_tetap" name="pembelian_aset_tetap"
                        value="{{ old('pembelian_aset_tetap', 0) }}" x-model="data.pembelian_aset_tetap"
                        @input="calculateTotals()">
                    @error('pembelian_aset_tetap')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="investasi_jangka_panjang" class="form-label">Investasi Jangka Panjang</label>
                    <input type="number"
                        class="form-control money-input @error('investasi_jangka_panjang') is-invalid @enderror"
                        id="investasi_jangka_panjang" name="investasi_jangka_panjang"
                        value="{{ old('investasi_jangka_panjang', 0) }}" x-model="data.investasi_jangka_panjang"
                        @input="calculateTotals()">
                    @error('investasi_jangka_panjang')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Kas Keluar Investasi</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_kas_keluar_investasi)" readonly>
                    <input type="hidden" name="total_kas_keluar_investasi"
                        x-bind:value="data.total_kas_keluar_investasi">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold text-success fs-5">KAS BERSIH INVESTASI</label>
                    <input type="text" class="form-control bg-success text-white fw-bold fs-5"
                        x-bind:value="formatCurrency(data.kas_bersih_investasi)" readonly>
                    <input type="hidden" name="kas_bersih_investasi" x-bind:value="data.kas_bersih_investasi">
                </div>
            </div>
        </div>
    </div>

    <!-- AKTIVITAS PENDANAAN -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="bi bi-bank me-2"></i>
                AKTIVITAS PENDANAAN
            </h5>
        </div>
        <div class="card-body">
            <!-- Kas Masuk Pendanaan -->
            <h6 class="text-warning border-bottom pb-2 mb-3">Kas Masuk dari Aktivitas Pendanaan</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="penerimaan_simpanan_anggota" class="form-label">Penerimaan Simpanan Anggota</label>
                    <input type="number"
                        class="form-control money-input @error('penerimaan_simpanan_anggota') is-invalid @enderror"
                        id="penerimaan_simpanan_anggota" name="penerimaan_simpanan_anggota"
                        value="{{ old('penerimaan_simpanan_anggota', 0) }}" x-model="data.penerimaan_simpanan_anggota"
                        @input="calculateTotals()">
                    @error('penerimaan_simpanan_anggota')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="penerimaan_pinjaman_bank" class="form-label">Penerimaan Pinjaman Bank</label>
                    <input type="number"
                        class="form-control money-input @error('penerimaan_pinjaman_bank') is-invalid @enderror"
                        id="penerimaan_pinjaman_bank" name="penerimaan_pinjaman_bank"
                        value="{{ old('penerimaan_pinjaman_bank', 0) }}" x-model="data.penerimaan_pinjaman_bank"
                        @input="calculateTotals()">
                    @error('penerimaan_pinjaman_bank')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="penambahan_modal" class="form-label">Penambahan Modal</label>
                    <input type="number"
                        class="form-control money-input @error('penambahan_modal') is-invalid @enderror"
                        id="penambahan_modal" name="penambahan_modal" value="{{ old('penambahan_modal', 0) }}"
                        x-model="data.penambahan_modal" @input="calculateTotals()">
                    @error('penambahan_modal')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Kas Masuk Pendanaan</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_kas_masuk_pendanaan)" readonly>
                    <input type="hidden" name="total_kas_masuk_pendanaan" x-bind:value="data.total_kas_masuk_pendanaan">
                </div>
            </div>

            <!-- Kas Keluar Pendanaan -->
            <h6 class="text-warning border-bottom pb-2 mb-3 mt-4">Kas Keluar dari Aktivitas Pendanaan</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="pembayaran_simpanan_anggota" class="form-label">Pembayaran Simpanan Anggota</label>
                    <input type="number"
                        class="form-control money-input @error('pembayaran_simpanan_anggota') is-invalid @enderror"
                        id="pembayaran_simpanan_anggota" name="pembayaran_simpanan_anggota"
                        value="{{ old('pembayaran_simpanan_anggota', 0) }}" x-model="data.pembayaran_simpanan_anggota"
                        @input="calculateTotals()">
                    @error('pembayaran_simpanan_anggota')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="pembayaran_pinjaman_bank" class="form-label">Pembayaran Pinjaman Bank</label>
                    <input type="number"
                        class="form-control money-input @error('pembayaran_pinjaman_bank') is-invalid @enderror"
                        id="pembayaran_pinjaman_bank" name="pembayaran_pinjaman_bank"
                        value="{{ old('pembayaran_pinjaman_bank', 0) }}" x-model="data.pembayaran_pinjaman_bank"
                        @input="calculateTotals()">
                    @error('pembayaran_pinjaman_bank')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="pembayaran_shu" class="form-label">Pembayaran SHU</label>
                    <input type="number" class="form-control money-input @error('pembayaran_shu') is-invalid @enderror"
                        id="pembayaran_shu" name="pembayaran_shu" value="{{ old('pembayaran_shu', 0) }}"
                        x-model="data.pembayaran_shu" @input="calculateTotals()">
                    @error('pembayaran_shu')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Total Kas Keluar Pendanaan</label>
                    <input type="text" class="form-control bg-light fw-bold"
                        x-bind:value="formatCurrency(data.total_kas_keluar_pendanaan)" readonly>
                    <input type="hidden" name="total_kas_keluar_pendanaan"
                        x-bind:value="data.total_kas_keluar_pendanaan">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold text-warning fs-5">KAS BERSIH PENDANAAN</label>
                    <input type="text" class="form-control bg-warning text-dark fw-bold fs-5"
                        x-bind:value="formatCurrency(data.kas_bersih_pendanaan)" readonly>
                    <input type="hidden" name="kas_bersih_pendanaan" x-bind:value="data.kas_bersih_pendanaan">
                </div>
            </div>
        </div>
    </div>

    <!-- RINGKASAN ARUS KAS -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="bi bi-calculator me-2"></i>
                RINGKASAN ARUS KAS
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Kas Bersih Operasional</label>
                    <input type="text" class="form-control bg-primary text-white fw-bold"
                        x-bind:value="formatCurrency(data.kas_bersih_operasional)" readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Kas Bersih Investasi</label>
                    <input type="text" class="form-control bg-success text-white fw-bold"
                        x-bind:value="formatCurrency(data.kas_bersih_investasi)" readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Kas Bersih Pendanaan</label>
                    <input type="text" class="form-control bg-warning text-dark fw-bold"
                        x-bind:value="formatCurrency(data.kas_bersih_pendanaan)" readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold fs-5">KENAIKAN/PENURUNAN KAS</label>
                    <input type="text" class="form-control fw-bold fs-5"
                        x-bind:class="netCashFlow >= 0 ? 'bg-success text-white' : 'bg-danger text-white'"
                        x-bind:value="formatCurrency(Math.abs(netCashFlow))" readonly>
                    <input type="hidden" name="net_cash_flow" x-bind:value="netCashFlow">
                </div>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <a href="{{ route('financial.cash-flow.index') }}" class="btn btn-outline-secondary">
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
    function cashFlowForm() {
    return {
        data: {
            // Aktivitas Operasional - Kas Masuk
            penerimaan_dari_anggota: 0,
            penerimaan_bunga_pinjaman: 0,
            penerimaan_jasa_lainnya: 0,
            total_kas_masuk_operasional: 0,

            // Aktivitas Operasional - Kas Keluar
            pembayaran_bunga_simpanan: 0,
            pembayaran_beban_operasional: 0,
            pembayaran_gaji_karyawan: 0,
            total_kas_keluar_operasional: 0,
            kas_bersih_operasional: 0,

            // Aktivitas Investasi - Kas Masuk
            penjualan_aset_tetap: 0,
            penerimaan_dividen: 0,
            total_kas_masuk_investasi: 0,

            // Aktivitas Investasi - Kas Keluar
            pembelian_aset_tetap: 0,
            investasi_jangka_panjang: 0,
            total_kas_keluar_investasi: 0,
            kas_bersih_investasi: 0,

            // Aktivitas Pendanaan - Kas Masuk
            penerimaan_simpanan_anggota: 0,
            penerimaan_pinjaman_bank: 0,
            penambahan_modal: 0,
            total_kas_masuk_pendanaan: 0,

            // Aktivitas Pendanaan - Kas Keluar
            pembayaran_simpanan_anggota: 0,
            pembayaran_pinjaman_bank: 0,
            pembayaran_shu: 0,
            total_kas_keluar_pendanaan: 0,
            kas_bersih_pendanaan: 0
        },

        get netCashFlow() {
            return this.data.kas_bersih_operasional + this.data.kas_bersih_investasi + this.data.kas_bersih_pendanaan;
        },

        calculateTotals() {
            // Calculate Operasional
            this.data.total_kas_masuk_operasional =
                parseFloat(this.data.penerimaan_dari_anggota || 0) +
                parseFloat(this.data.penerimaan_bunga_pinjaman || 0) +
                parseFloat(this.data.penerimaan_jasa_lainnya || 0);

            this.data.total_kas_keluar_operasional =
                parseFloat(this.data.pembayaran_bunga_simpanan || 0) +
                parseFloat(this.data.pembayaran_beban_operasional || 0) +
                parseFloat(this.data.pembayaran_gaji_karyawan || 0);

            this.data.kas_bersih_operasional = this.data.total_kas_masuk_operasional - this.data.total_kas_keluar_operasional;

            // Calculate Investasi
            this.data.total_kas_masuk_investasi =
                parseFloat(this.data.penjualan_aset_tetap || 0) +
                parseFloat(this.data.penerimaan_dividen || 0);

            this.data.total_kas_keluar_investasi =
                parseFloat(this.data.pembelian_aset_tetap || 0) +
                parseFloat(this.data.investasi_jangka_panjang || 0);

            this.data.kas_bersih_investasi = this.data.total_kas_masuk_investasi - this.data.total_kas_keluar_investasi;

            // Calculate Pendanaan
            this.data.total_kas_masuk_pendanaan =
                parseFloat(this.data.penerimaan_simpanan_anggota || 0) +
                parseFloat(this.data.penerimaan_pinjaman_bank || 0) +
                parseFloat(this.data.penambahan_modal || 0);

            this.data.total_kas_keluar_pendanaan =
                parseFloat(this.data.pembayaran_simpanan_anggota || 0) +
                parseFloat(this.data.pembayaran_pinjaman_bank || 0) +
                parseFloat(this.data.pembayaran_shu || 0);

            this.data.kas_bersih_pendanaan = this.data.total_kas_masuk_pendanaan - this.data.total_kas_keluar_pendanaan;
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
    const formData = new FormData(document.getElementById('cashFlowForm'));

    fetch('/api/financial/auto-save/cash-flow/' + formData.get('reporting_year'), {
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
