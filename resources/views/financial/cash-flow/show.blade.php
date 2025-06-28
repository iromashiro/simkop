{{-- resources/views/financial/cash-flow/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Detail Laporan Arus Kas')

@php
$header = [
'title' => 'Laporan Arus Kas Tahun ' . $report->reporting_year,
'subtitle' => 'Laporan pergerakan kas per ' . $report->report_date->format('d F Y'),
'actions' => $report->status === 'draft' ?
'<a href="' . route('financial.cash-flow.edit', $report) . '" class="btn btn-primary">
    <i class="bi bi-pencil me-1"></i> Edit Laporan
</a>' : ''
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Laporan Arus Kas', 'url' => route('financial.cash-flow.index')],
['title' => 'Tahun ' . $report->reporting_year, 'url' => route('financial.cash-flow.show', $report)]
];
@endphp

@section('content')
<!-- Report Status -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h6 class="text-muted mb-1">Status Laporan</h6>
                @switch($report->status)
                @case('draft')
                <span class="badge bg-secondary fs-6">Draft</span>
                <p class="text-muted mb-0 mt-2">Laporan masih dalam tahap penyusunan</p>
                @break
                @case('submitted')
                <span class="badge bg-warning fs-6">Terkirim</span>
                <p class="text-muted mb-0 mt-2">Laporan telah dikirim dan menunggu persetujuan</p>
                @break
                @case('approved')
                <span class="badge bg-success fs-6">Disetujui</span>
                <p class="text-muted mb-0 mt-2">Laporan telah disetujui pada
                    {{ $report->approved_at?->format('d F Y H:i') }}</p>
                @break
                @case('rejected')
                <span class="badge bg-danger fs-6">Ditolak</span>
                <p class="text-muted mb-0 mt-2">Laporan ditolak pada {{ $report->rejected_at?->format('d F Y H:i') }}
                </p>
                @if($report->rejection_reason)
                <div class="alert alert-danger mt-2">
                    <strong>Alasan penolakan:</strong> {{ $report->rejection_reason }}
                </div>
                @endif
                @break
                @endswitch
            </div>
            <div class="col-md-4 text-end">
                @if($report->status === 'draft')
                <form action="{{ route('financial.cash-flow.submit', $report) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success"
                        onclick="return confirm('Apakah Anda yakin ingin mengirim laporan ini?')">
                        <i class="bi bi-send me-1"></i> Kirim Laporan
                    </button>
                </form>
                @endif
                @if($report->status === 'approved')
                <a href="{{ route('reports.export.pdf', ['type' => 'cash-flow', 'id' => $report->id]) }}"
                    class="btn btn-primary">
                    <i class="bi bi-download me-1"></i> Download PDF
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Cash Flow Report -->
<div class="card">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">
            <i class="bi bi-cash-stack me-2"></i>
            LAPORAN ARUS KAS
        </h5>
        <small>{{ auth()->user()->cooperative->name }} - Tahun {{ $report->reporting_year }}</small>
    </div>
    <div class="card-body">
        <!-- AKTIVITAS OPERASIONAL -->
        <div class="mb-4">
            <h6 class="text-primary border-bottom pb-2 mb-3 fw-bold">
                <i class="bi bi-gear me-1"></i>
                AKTIVITAS OPERASIONAL
            </h6>

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
            <h6 class="text-success border-bottom pb-2 mb-3 fw-bold">
                <i class="bi bi-graph-up me-1"></i>
                AKTIVITAS INVESTASI
            </h6>

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
            <h6 class="text-warning border-bottom pb-2 mb-3 fw-bold">
                <i class="bi bi-bank me-1"></i>
                AKTIVITAS PENDANAAN
            </h6>

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
        @php
        $netCashFlow = ($totals['kas_bersih_operasional'] ?? 0) + ($totals['kas_bersih_investasi'] ?? 0) +
        ($totals['kas_bersih_pendanaan'] ?? 0);
        @endphp

        <div class="mb-4">
            <h6 class="border-bottom pb-2 mb-3 fw-bold {{ $netCashFlow >= 0 ? 'text-success' : 'text-danger' }}">
                <i class="bi bi-calculator me-1"></i>
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
            <h6 class="text-muted">Catatan:</h6>
            <div class="bg-light p-3 rounded">
                {{ $report->notes }}
            </div>
        </div>
        @endif

        <!-- Report Info -->
        <div class="row mt-4 pt-4 border-top">
            <div class="col-md-6">
                <small class="text-muted">
                    <strong>Dibuat:</strong> {{ $report->created_at->format('d F Y H:i') }}<br>
                    <strong>Terakhir diubah:</strong> {{ $report->updated_at->format('d F Y H:i') }}
                </small>
            </div>
            <div class="col-md-6 text-end">
                @if($report->submitted_at)
                <small class="text-muted">
                    <strong>Dikirim:</strong> {{ $report->submitted_at->format('d F Y H:i') }}
                </small>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
