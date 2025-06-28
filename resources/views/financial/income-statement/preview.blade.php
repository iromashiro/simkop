{{-- resources/views/financial/income-statement/preview.blade.php --}}
@extends('layouts.app')

@section('title', 'Preview Laporan Perhitungan Hasil Usaha')

@php
$header = [
'title' => 'Preview Laporan Perhitungan Hasil Usaha Tahun ' . $report->reporting_year,
'subtitle' => 'Pratinjau sebelum mengirim laporan'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Laporan Perhitungan Hasil Usaha', 'url' => route('financial.income-statement.index')],
['title' => 'Tahun ' . $report->reporting_year, 'url' => route('financial.income-statement.show', $report)],
['title' => 'Preview', 'url' => route('financial.income-statement.preview', $report)]
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
                    <a href="{{ route('financial.income-statement.edit', $report) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </a>
                    <form action="{{ route('financial.income-statement.submit', $report) }}" method="POST"
                        class="d-inline">
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

<!-- Print-ready Income Statement -->
<div class="card" id="printableReport">
    <div class="card-body">
        <!-- Report Header -->
        <div class="text-center mb-4">
            <h4 class="fw-bold">{{ auth()->user()->cooperative->name }}</h4>
            <h5 class="text-primary">LAPORAN PERHITUNGAN HASIL USAHA</h5>
            <p class="text-muted">Tahun {{ $report->reporting_year }}</p>
        </div>

        <!-- Income Statement Content -->
        @php
        $netIncome = ($totals['total_pendapatan'] ?? 0) - ($totals['total_beban'] ?? 0);
        @endphp

        <!-- PENDAPATAN -->
        <div class="mb-4">
            <h6 class="text-success border-bottom pb-2 mb-3 fw-bold">PENDAPATAN</h6>

            <!-- Pendapatan Operasional -->
            <div class="mb-4">
                <h6 class="text-secondary mb-3">Pendapatan Operasional</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td style="padding-left: 20px;">Pendapatan Jasa Simpan Pinjam</td>
                        <td class="text-end font-monospace">
                            {{ number_format($accounts['revenue']['operational']['pendapatan_jasa_simpan_pinjam'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Pendapatan Administrasi</td>
                        <td class="text-end font-monospace">
                            {{ number_format($accounts['revenue']['operational']['pendapatan_administrasi'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Pendapatan Provisi</td>
                        <td class="text-end font-monospace">
                            {{ number_format($accounts['revenue']['operational']['pendapatan_provisi'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Pendapatan Operasional Lainnya</td>
                        <td class="text-end font-monospace">
                            {{ number_format($accounts['revenue']['operational']['pendapatan_operasional_lainnya'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Total Pendapatan Operasional</td>
                        <td class="text-end font-monospace fw-bold">
                            {{ number_format($totals['total_pendapatan_operasional'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>

            <!-- Pendapatan Non-Operasional -->
            <div class="mb-4">
                <h6 class="text-secondary mb-3">Pendapatan Non-Operasional</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td style="padding-left: 20px;">Pendapatan Bunga Bank</td>
                        <td class="text-end font-monospace">
                            {{ number_format($accounts['revenue']['non_operational']['pendapatan_bunga_bank'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Pendapatan Non-Operasional Lainnya</td>
                        <td class="text-end font-monospace">
                            {{ number_format($accounts['revenue']['non_operational']['pendapatan_non_operasional_lainnya'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Total Pendapatan Non-Operasional</td>
                        <td class="text-end font-monospace fw-bold">
                            {{ number_format($totals['total_pendapatan_non_operasional'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>

            <div class="bg-success text-white p-3 rounded mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">TOTAL PENDAPATAN</h6>
                    <h5 class="mb-0 font-monospace fw-bold">
                        {{ number_format($totals['total_pendapatan'] ?? 0, 0, ',', '.') }}</h5>
                </div>
            </div>
        </div>

        <!-- BEBAN -->
        <div class="mb-4">
            <h6 class="text-danger border-bottom pb-2 mb-3 fw-bold">BEBAN</h6>

            <!-- Beban Operasional -->
            <div class="mb-4">
                <h6 class="text-secondary mb-3">Beban Operasional</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td style="padding-left: 20px;">Beban Bunga Simpanan</td>
                        <td class="text-end font-monospace">
                            {{ number_format($accounts['expenses']['operational']['beban_bunga_simpanan'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Beban Administrasi & Umum</td>
                        <td class="text-end font-monospace">
                            {{ number_format($accounts['expenses']['operational']['beban_administrasi_umum'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Beban Personalia</td>
                        <td class="text-end font-monospace">
                            {{ number_format($accounts['expenses']['operational']['beban_personalia'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Beban Penyusutan</td>
                        <td class="text-end font-monospace">
                            {{ number_format($accounts['expenses']['operational']['beban_penyusutan'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Beban Operasional Lainnya</td>
                        <td class="text-end font-monospace">
                            {{ number_format($accounts['expenses']['operational']['beban_operasional_lainnya'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Total Beban Operasional</td>
                        <td class="text-end font-monospace fw-bold">
                            {{ number_format($totals['total_beban_operasional'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>

            <!-- Beban Non-Operasional -->
            <div class="mb-4">
                <h6 class="text-secondary mb-3">Beban Non-Operasional</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td style="padding-left: 20px;">Beban Bunga Bank</td>
                        <td class="text-end font-monospace">
                            {{ number_format($accounts['expenses']['non_operational']['beban_bunga_bank'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left: 20px;">Beban Non-Operasional Lainnya</td>
                        <td class="text-end font-monospace">
                            {{ number_format($accounts['expenses']['non_operational']['beban_non_operasional_lainnya'] ?? 0, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Total Beban Non-Operasional</td>
                        <td class="text-end font-monospace fw-bold">
                            {{ number_format($totals['total_beban_non_operasional'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>

            <div class="bg-danger text-white p-3 rounded mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">TOTAL BEBAN</h6>
                    <h5 class="mb-0 font-monospace fw-bold">
                        {{ number_format($totals['total_beban'] ?? 0, 0, ',', '.') }}</h5>
                </div>
            </div>
        </div>

        <!-- LABA/RUGI BERSIH -->
        <div class="mb-4">
            <h6 class="border-bottom pb-2 mb-3 fw-bold {{ $netIncome >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $netIncome >= 0 ? 'LABA BERSIH' : 'RUGI BERSIH' }}
            </h6>

            <div class="row">
                <div class="col-md-4">
                    <div class="bg-success text-white p-3 rounded text-center">
                        <h6 class="mb-1">Total Pendapatan</h6>
                        <h5 class="mb-0 font-monospace">
                            {{ number_format($totals['total_pendapatan'] ?? 0, 0, ',', '.') }}</h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-danger text-white p-3 rounded text-center">
                        <h6 class="mb-1">Total Beban</h6>
                        <h5 class="mb-0 font-monospace">{{ number_format($totals['total_beban'] ?? 0, 0, ',', '.') }}
                        </h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded text-center {{ $netIncome >= 0 ? 'bg-success' : 'bg-danger' }} text-white">
                        <h6 class="mb-1">{{ $netIncome >= 0 ? 'Laba' : 'Rugi' }} Bersih</h6>
                        <h4 class="mb-0 font-monospace">{{ number_format(abs($netIncome), 0, ',', '.') }}</h4>
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
            <a href="{{ route('financial.income-statement.show', $report) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <div>
                <button type="button" class="btn btn-outline-primary me-2" onclick="printReport()">
                    <i class="bi bi-printer me-1"></i> Cetak
                </button>
                <form action="{{ route('financial.income-statement.submit', $report) }}" method="POST" class="d-inline">
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
