{{-- resources/views/financial/income-statement/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Detail Laporan Perhitungan Hasil Usaha')

@php
$header = [
'title' => 'Laporan Perhitungan Hasil Usaha Tahun ' . $report->reporting_year,
'subtitle' => 'Laporan laba rugi per ' . $report->report_date->format('d F Y'),
'actions' => $report->status === 'draft' ?
'<a href="' . route('financial.income-statement.edit', $report) . '" class="btn btn-primary">
    <i class="bi bi-pencil me-1"></i> Edit Laporan
</a>' : ''
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Laporan Perhitungan Hasil Usaha', 'url' => route('financial.income-statement.index')],
['title' => 'Tahun ' . $report->reporting_year, 'url' => route('financial.income-statement.show', $report)]
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
                <form action="{{ route('financial.income-statement.submit', $report) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success"
                        onclick="return confirm('Apakah Anda yakin ingin mengirim laporan ini?')">
                        <i class="bi bi-send me-1"></i> Kirim Laporan
                    </button>
                </form>
                @endif
                @if($report->status === 'approved')
                <a href="{{ route('reports.export.pdf', ['type' => 'income-statement', 'id' => $report->id]) }}"
                    class="btn btn-primary">
                    <i class="bi bi-download me-1"></i> Download PDF
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Income Statement Report -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="bi bi-graph-up me-2"></i>
            LAPORAN PERHITUNGAN HASIL USAHA
        </h5>
        <small>{{ auth()->user()->cooperative->name }} - Tahun {{ $report->reporting_year }}</small>
    </div>
    <div class="card-body">
        <!-- PENDAPATAN -->
        <div class="mb-4">
            <h6 class="text-success border-bottom pb-2 mb-3 fw-bold">
                <i class="bi bi-arrow-up-circle me-1"></i>
                PENDAPATAN
            </h6>

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
            <h6 class="text-danger border-bottom pb-2 mb-3 fw-bold">
                <i class="bi bi-arrow-down-circle me-1"></i>
                BEBAN
            </h6>

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
        @php
        $netIncome = ($totals['total_pendapatan'] ?? 0) - ($totals['total_beban'] ?? 0);
        @endphp

        <div class="mb-4">
            <h6 class="border-bottom pb-2 mb-3 fw-bold {{ $netIncome >= 0 ? 'text-success' : 'text-danger' }}">
                <i class="bi bi-calculator me-1"></i>
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
