{{-- resources/views/financial/balance-sheet/preview.blade.php --}}
@extends('layouts.app')

@section('title', 'Preview Neraca')

@php
$header = [
'title' => 'Preview Neraca Tahun ' . $report->reporting_year,
'subtitle' => 'Pratinjau sebelum mengirim laporan'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Neraca', 'url' => route('financial.balance-sheet.index')],
['title' => 'Tahun ' . $report->reporting_year, 'url' => route('financial.balance-sheet.show',
$report->reporting_year)],
['title' => 'Preview', 'url' => route('financial.balance-sheet.preview', $report->reporting_year)]
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
                    <a href="{{ route('financial.balance-sheet.edit', $report->reporting_year) }}"
                        class="btn btn-outline-secondary">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </a>
                    <button type="button" class="btn btn-primary" onclick="submitReport()">
                        <i class="bi bi-send me-1"></i> Kirim Laporan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print-ready Balance Sheet -->
<div class="card" id="printableReport">
    <div class="card-body">
        <!-- Report Header -->
        <div class="text-center mb-4">
            <h4 class="fw-bold">{{ auth()->user()->cooperative->name }}</h4>
            <h5 class="text-primary">NERACA</h5>
            <p class="text-muted">Per {{ $report->report_date->format('d F Y') }}</p>
        </div>

        <!-- Balance Sheet Content -->
        <div class="row">
            <!-- ASET -->
            <div class="col-lg-6">
                <h6 class="text-primary border-bottom pb-2 mb-3 fw-bold">ASET</h6>

                <!-- Aset Lancar -->
                <div class="mb-4">
                    <h6 class="text-secondary mb-3">Aset Lancar</h6>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td style="padding-left: 20px;">Kas dan Bank</td>
                            <td class="text-end font-monospace">{{ number_format($report->kas_bank ?? 0, 0, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Piutang Anggota</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->piutang_anggota ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Piutang Non Anggota</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->piutang_non_anggota ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Persediaan</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->persediaan ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Aset Lancar Lainnya</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->aset_lancar_lainnya ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-bold">Total Aset Lancar</td>
                            <td class="text-end font-monospace fw-bold">
                                {{ number_format($report->total_aset_lancar ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </div>

                <!-- Aset Tidak Lancar -->
                <div class="mb-4">
                    <h6 class="text-secondary mb-3">Aset Tidak Lancar</h6>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td style="padding-left: 20px;">Investasi Jangka Panjang</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->investasi_jangka_panjang ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Aset Tetap</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->aset_tetap ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Akumulasi Penyusutan</td>
                            <td class="text-end font-monospace">
                                ({{ number_format($report->akumulasi_penyusutan ?? 0, 0, ',', '.') }})</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Aset Tidak Lancar Lainnya</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->aset_tidak_lancar_lainnya ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-bold">Total Aset Tidak Lancar</td>
                            <td class="text-end font-monospace fw-bold">
                                {{ number_format($report->total_aset_tidak_lancar ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </div>

                <div class="bg-primary text-white p-3 rounded">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">TOTAL ASET</h6>
                        <h5 class="mb-0 font-monospace fw-bold">
                            {{ number_format($report->total_aset ?? 0, 0, ',', '.') }}</h5>
                    </div>
                </div>
            </div>

            <!-- KEWAJIBAN & EKUITAS -->
            <div class="col-lg-6">
                <!-- KEWAJIBAN -->
                <h6 class="text-warning border-bottom pb-2 mb-3 fw-bold">KEWAJIBAN</h6>

                <!-- Kewajiban Jangka Pendek -->
                <div class="mb-4">
                    <h6 class="text-secondary mb-3">Kewajiban Jangka Pendek</h6>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td style="padding-left: 20px;">Simpanan Anggota</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->simpanan_anggota ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Simpanan Non Anggota</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->simpanan_non_anggota ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Hutang Bank</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->hutang_bank ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Hutang Lainnya</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->hutang_lainnya ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-bold">Total Kewajiban Jangka Pendek</td>
                            <td class="text-end font-monospace fw-bold">
                                {{ number_format($report->total_kewajiban_jangka_pendek ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </div>

                <!-- Kewajiban Jangka Panjang -->
                <div class="mb-4">
                    <h6 class="text-secondary mb-3">Kewajiban Jangka Panjang</h6>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td style="padding-left: 20px;">Hutang Jangka Panjang</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->hutang_jangka_panjang ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Kewajiban Lainnya</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->kewajiban_lainnya ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-bold">Total Kewajiban Jangka Panjang</td>
                            <td class="text-end font-monospace fw-bold">
                                {{ number_format($report->total_kewajiban_jangka_panjang ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </div>

                <div class="bg-warning text-dark p-3 rounded mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">TOTAL KEWAJIBAN</h6>
                        <h6 class="mb-0 font-monospace fw-bold">
                            {{ number_format($report->total_kewajiban ?? 0, 0, ',', '.') }}</h6>
                    </div>
                </div>

                <!-- EKUITAS -->
                <h6 class="text-success border-bottom pb-2 mb-3 fw-bold">EKUITAS</h6>

                <div class="mb-4">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td style="padding-left: 20px;">Simpanan Pokok</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->simpanan_pokok ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Simpanan Wajib</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->simpanan_wajib ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Cadangan</td>
                            <td class="text-end font-monospace">{{ number_format($report->cadangan ?? 0, 0, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">SHU Belum Dibagi</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->shu_belum_dibagi ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 20px;">Ekuitas Lainnya</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->ekuitas_lainnya ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-bold">Total Ekuitas</td>
                            <td class="text-end font-monospace fw-bold">
                                {{ number_format($report->total_ekuitas ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </div>

                <div class="bg-dark text-white p-3 rounded">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">TOTAL KEWAJIBAN + EKUITAS</h6>
                        <h5 class="mb-0 font-monospace fw-bold">
                            {{ number_format(($report->total_kewajiban ?? 0) + ($report->total_ekuitas ?? 0), 0, ',', '.') }}
                        </h5>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Check -->
        @php
        $totalAset = $report->total_aset ?? 0;
        $totalKewajibanEkuitas = ($report->total_kewajiban ?? 0) + ($report->total_ekuitas ?? 0);
        $isBalanced = abs($totalAset - $totalKewajibanEkuitas) < 1; @endphp @if(!$isBalanced) <div
            class="alert alert-danger mt-4">
            <h6><i class="bi bi-exclamation-triangle me-2"></i>Peringatan: Neraca Tidak Seimbang</h6>
            <p class="mb-0">
                Selisih: Rp {{ number_format(abs($totalAset - $totalKewajibanEkuitas), 0, ',', '.') }}<br>
                Silakan periksa kembali data sebelum mengirim laporan.
            </p>
    </div>
    @else
    <div class="alert alert-success mt-4">
        <h6><i class="bi bi-check-circle me-2"></i>Neraca Seimbang</h6>
        <p class="mb-0">Total Aset sama dengan Total Kewajiban + Ekuitas. Laporan siap dikirim.</p>
    </div>
    @endif

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
            <a href="{{ route('financial.balance-sheet.show', $report->reporting_year) }}"
                class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <div>
                <button type="button" class="btn btn-outline-primary me-2" onclick="printReport()">
                    <i class="bi bi-printer me-1"></i> Cetak
                </button>
                <button type="button" class="btn btn-primary" onclick="submitReport()"
                    {{ !$isBalanced ? 'disabled' : '' }}>
                    <i class="bi bi-send me-1"></i> Kirim Laporan
                </button>
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

function submitReport() {
    if (confirm('Apakah Anda yakin ingin mengirim laporan neraca tahun {{ $report->reporting_year }}? Setelah dikirim, laporan tidak dapat diubah.')) {
        fetch(`/financial/balance-sheet/{{ $report->reporting_year }}/submit`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': SIMKOP.csrfToken,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '{{ route("financial.balance-sheet.show", $report->reporting_year) }}';
            } else {
                alert('Gagal mengirim laporan: ' + data.message);
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan: ' + error.message);
        });
    }
}
</script>
@endpush
