{{-- resources/views/financial/cash-flow/preview.blade.php --}}
@extends('layouts.app')

@section('title', 'Preview Laporan Arus Kas')

@php
$header = [
'title' => 'Preview Laporan Arus Kas Tahun ' . $report->reporting_year,
'subtitle' => 'Pratinjau sebelum mengirim laporan'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Laporan Arus Kas', 'url' => route('financial.cash-flow.index')],
['title' => 'Tahun ' . $report->reporting_year, 'url' => route('financial.cash-flow.show', $report)],
['title' => 'Preview', 'url' => route('financial.cash-flow.preview', $report)]
];
@endphp

@section('content')
<!-- Preview Actions -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h6 class="text-muted mb-1">Preview Laporan</h6>
                <p class="mb-0">Periksa kembali data sebelum mengirim laporan ke Admin Dinas</p>
            </div>
            <div class="col-md-4 text-end">
                <div class="btn-group">
                    <a href="{{ route('financial.cash-flow.edit', $report) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </a>
                    <form action="{{ route('financial.cash-flow.submit', $report) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Apakah Anda yakin ingin mengirim laporan ini?')">
                            <i class="bi bi-send me-1"></i> Kirim Laporan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print-ready Cash Flow -->
<div class="card" id="printableReport">
    <div class="card-body">
        <!-- Report Header -->
        <div class="text-center mb-4">
            <h4 class="fw-bold">{{ auth()->user()->cooperative->name }}</h4>
            <h5 class="text-primary">LAPORAN ARUS KAS</h5>
            <p class="text-muted">Tahun {{ $report->reporting_year }}</p>
        </div>

        <!-- Cash Flow Content -->
        @php
        $netCashFlow = ($totals['kas_bersih_operasional'] ?? 0) + ($totals['kas_bersih_investasi'] ?? 0) +
        ($totals['kas_bersih_pendanaan'] ?? 0);
        @endphp

        <!-- AKTIVITAS OPERASIONAL -->
        <div class="mb-4">
            <h6 class="text-primary border-bottom pb-2 mb-3 fw-bold">AKTIVITAS OPERASIONAL</h6>

            <!-- Kas Masuk Operasional -->
            <div class="mb-4">
                <h6 class="text-secondary mb-3">Kas Masuk dari Aktivitas Operasional</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td style="padding-left: 20px;">Penerimaan dari Anggota</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['operating']['inflow']['penerimaan_dari_anggota'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Penerimaan Bunga Pinjaman</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['operating']['inflow']['penerimaan_bunga_pinjaman'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Penerimaan Jasa Lainnya</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['operating']['inflow']['penerimaan_jasa_lainnya'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Total Kas Masuk Operasional</td>
                        <td class="text-end font-monospace fw-bold">
                            {{ number_format($totals['total_kas_masuk_operasional'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>

            <!-- Kas Keluar Operasional -->
            <div class="mb-4">
                <h6 class="text-secondary mb-3">Kas Keluar dari Aktivitas Operasional</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td style="padding-left: 20px;">Pembayaran Bunga Simpanan</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['operating']['outflow']['pembayaran_bunga_simpanan'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Pembayaran Beban Operasional</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['operating']['outflow']['pembayaran_beban_operasional'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Pembayaran Gaji Karyawan</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['operating']['outflow']['pembayaran_gaji_karyawan'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Total Kas Keluar Operasional</td>
                        <td class="text-end font-monospace fw-bold">
                            {{ number_format($totals['total_kas_keluar_operasional'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>

            <div class="bg-primary text-white p-3 rounded mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">KAS BERSIH DARI AKTIVITAS OPERASIONAL</h6>
                    <h5 class="mb-0 font-monospace fw-bold">
                        {{ number_format($totals['kas_bersih_operasional'] ?? 0, 0, ',', '.') }}</h5>
                </div>
            </div>
        </div>

        <!-- AKTIVITAS INVESTASI -->
        <div class="mb-4">
            <h6 class="text-success border-bottom pb-2 mb-3 fw-bold">AKTIVITAS INVESTASI</h6>

            <!-- Kas Masuk Investasi -->
            <div class="mb-4">
                <h6 class="text-secondary mb-3">Kas Masuk dari Aktivitas Investasi</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td style="padding-left: 20px;">Penjualan Aset Tetap</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['investing']['inflow']['penjualan_aset_tetap'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Penerimaan Dividen</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['investing']['inflow']['penerimaan_dividen'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Total Kas Masuk Investasi</td>
                        <td class="text-end font-monospace fw-bold">
                            {{ number_format($totals['total_kas_masuk_investasi'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>

            <!-- Kas Keluar Investasi -->
            <div class="mb-4">
                <h6 class="text-secondary mb-3">Kas Keluar dari Aktivitas Investasi</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td style="padding-left: 20px;">Pembelian Aset Tetap</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['investing']['outflow']['pembelian_aset_tetap'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Investasi Jangka Panjang</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['investing']['outflow']['investasi_jangka_panjang'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Total Kas Keluar Investasi</td>
                        <td class="text-end font-monospace fw-bold">
                            {{ number_format($totals['total_kas_keluar_investasi'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>

            <div class="bg-success text-white p-3 rounded mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">KAS BERSIH DARI AKTIVITAS INVESTASI</h6>
                    <h5 class="mb-0 font-monospace fw-bold">
                        {{ number_format($totals['kas_bersih_investasi'] ?? 0, 0, ',', '.') }}</h5>
                </div>
            </div>
        </div>

        <!-- AKTIVITAS PENDANAAN -->
        <div class="mb-4">
            <h6 class="text-warning border-bottom pb-2 mb-3 fw-bold">AKTIVITAS PENDANAAN</h6>

            <!-- Kas Masuk Pendanaan -->
            <div class="mb-4">
                <h6 class="text-secondary mb-3">Kas Masuk dari Aktivitas Pendanaan</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td style="padding-left: 20px;">Penerimaan Simpanan Anggota</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['financing']['inflow']['penerimaan_simpanan_anggota'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Penerimaan Pinjaman Bank</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['financing']['inflow']['penerimaan_pinjaman_bank'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Penambahan Modal</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['financing']['inflow']['penambahan_modal'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Total Kas Masuk Pendanaan</td>
                        <td class="text-end font-monospace fw-bold">
                            {{ number_format($totals['total_kas_masuk_pendanaan'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>

            <!-- Kas Keluar Pendanaan -->
            <div class="mb-4">
                <h6 class="text-secondary mb-3">Kas Keluar dari Aktivitas Pendanaan</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td style="padding-left: 20px;">Pembayaran Simpanan Anggota</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['financing']['outflow']['pembayaran_simpanan_anggota'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Pembayaran Pinjaman Bank</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['financing']['outflow']['pembayaran_pinjaman_bank'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Pembayaran SHU</td>
                        <td class="text-end font-monospace">
                            {{ number_format($activities['financing']['outflow']['pembayaran_shu'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Total Kas Keluar Pendanaan</td>
                        <td class="text-end font-monospace fw-bold">
                            {{ number_format($totals['total_kas_keluar_pendanaan'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>

            <div class="bg-warning text-dark p-3 rounded mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">KAS BERSIH DARI AKTIVITAS PENDANAAN</h6>
                    <h5 class="mb-0 font-monospace fw-bold">
                        {{ number_format($totals['kas_bersih_pendanaan'] ?? 0, 0, ',', '.') }}</h5>
                </div>
            </div>
        </div>

        <!-- RINGKASAN ARUS KAS -->
        <div class="mb-4">
            <h6 class="border-bottom pb-2 mb-3 fw-bold {{ $netCashFlow >= 0 ? 'text-success' : 'text-danger' }}">
                RINGKASAN ARUS KAS
            </h6>

            <div class="row">
                <div class="col-md-3">
                    <div class="bg-primary text-white p-3 rounded text-center">
                        <h6 class="mb-1">Kas Operasional</h6>
                        <h5 class="mb-0 font-monospace">
                            {{ number_format($totals['kas_bersih_operasional'] ?? 0, 0, ',', '.') }}</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="bg-success text-white p-3 rounded text-center">
                        <h6 class="mb-1">Kas Investasi</h6>
                        <h5 class="mb-0 font-monospace">
                            {{ number_format($totals['kas_bersih_investasi'] ?? 0, 0, ',', '.') }}</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="bg-warning text-dark p-3 rounded text-center">
                        <h6 class="mb-1">Kas Pendanaan</h6>
                        <h5 class="mb-0 font-monospace">
                            {{ number_format($totals['kas_bersih_pendanaan'] ?? 0, 0, ',', '.') }}</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div
                        class="p-3 rounded text-center {{ $netCashFlow >= 0 ? 'bg-success' : 'bg-danger' }} text-white">
                        <h6 class="mb-1">{{ $netCashFlow >= 0 ? 'Kenaikan' : 'Penurunan' }} Kas</h6>
                        <h4 class="mb-0 font-monospace">{{ number_format(abs($netCashFlow), 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes -->
        @if($report->notes)
        <div class="mt-4">
            <h6 class="fw-bold">Catatan:</h6>
            <div class="bg-light p-3 rounded">
                {{ $report->notes }}
            </div>
        </div>
        @endif

        <!-- Report Footer -->
        <div class="row mt-5 pt-4 border-top">
            <div class="col-md-6">
                <div class="text-center">
                    <p class="mb-1">Mengetahui,</p>
                    <p class="mb-5">Ketua Koperasi</p>
                    <p class="mb-0 border-top d-inline-block px-4">
                        {{ auth()->user()->cooperative->chairman_name ?? '(Nama Ketua)' }}
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="text-center">
                    <p class="mb-1">{{ auth()->user()->cooperative->address ?? 'Kota' }},
                        {{ $report->report_date->format('d F Y') }}</p>
                    <p class="mb-5">Bendahara</p>
                    <p class="mb-0 border-top d-inline-block px-4">
                        {{ auth()->user()->name }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="card mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <a href="{{ route('financial.cash-flow.show', $report) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <div>
                <button type="button" class="btn btn-outline-primary me-2" onclick="printReport()">
                    <i class="bi bi-printer me-1"></i> Cetak
                </button>
                <form action="{{ route('financial.cash-flow.submit', $report) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary"
                        onclick="return confirm('Apakah Anda yakin ingin mengirim laporan ini?')">
                        <i class="bi bi-send me-1"></i> Kirim Laporan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    @media print {
        .card {
            border: none !important;
            box-shadow: none !important;
        }

        .btn,
        .card:last-child {
            display: none !important;
        }

        body {
            font-size: 12px;
        }

        h4,
        h5,
        h6 {
            font-size: 14px;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    function printReport() {
    window.print();
}
</script>
@endpush
