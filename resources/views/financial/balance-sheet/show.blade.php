{{-- resources/views/financial/balance-sheet/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Detail Neraca')

@php
$header = [
'title' => 'Neraca Tahun ' . $report->reporting_year,
'subtitle' => 'Laporan posisi keuangan per ' . $report->report_date->format('d F Y'),
'actions' => $report->status === 'draft' ?
'<a href="' . route('financial.balance-sheet.edit', $report->reporting_year) . '" class="btn btn-primary">
    <i class="bi bi-pencil me-1"></i> Edit Neraca
</a>' : ''
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Neraca', 'url' => route('financial.balance-sheet.index')],
['title' => 'Tahun ' . $report->reporting_year, 'url' => route('financial.balance-sheet.show', $report->reporting_year)]
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
                <button type="button" class="btn btn-success" onclick="submitReport()">
                    <i class="bi bi-send me-1"></i> Kirim Laporan
                </button>
                @endif
                @if($report->status === 'approved')
                <a href="{{ route('reports.export.pdf', ['type' => 'balance-sheet', 'year' => $report->reporting_year]) }}"
                    class="btn btn-primary">
                    <i class="bi bi-download me-1"></i> Download PDF
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Balance Sheet Report -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="bi bi-clipboard-data me-2"></i>
            NERACA
        </h5>
        <small>{{ auth()->user()->cooperative->name }} - Per {{ $report->report_date->format('d F Y') }}</small>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- ASET -->
            <div class="col-lg-6 mb-4">
                <h6 class="text-primary border-bottom pb-2 mb-3">
                    <i class="bi bi-building me-1"></i>
                    ASET
                </h6>

                <!-- Aset Lancar -->
                <div class="mb-4">
                    <h6 class="text-secondary mb-3">Aset Lancar</h6>
                    <table class="table table-sm">
                        <tr>
                            <td>Kas dan Bank</td>
                            <td class="text-end font-monospace">{{ number_format($report->kas_bank ?? 0, 0, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td>Piutang Anggota</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->piutang_anggota ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Piutang Non Anggota</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->piutang_non_anggota ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Persediaan</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->persediaan ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Aset Lancar Lainnya</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->aset_lancar_lainnya ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="table-light fw-bold">
                            <td>Total Aset Lancar</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->total_aset_lancar ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </div>

                <!-- Aset Tidak Lancar -->
                <div class="mb-4">
                    <h6 class="text-secondary mb-3">Aset Tidak Lancar</h6>
                    <table class="table table-sm">
                        <tr>
                            <td>Investasi Jangka Panjang</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->investasi_jangka_panjang ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Aset Tetap</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->aset_tetap ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Akumulasi Penyusutan</td>
                            <td class="text-end font-monospace">
                                ({{ number_format($report->akumulasi_penyusutan ?? 0, 0, ',', '.') }})</td>
                        </tr>
                        <tr>
                            <td>Aset Tidak Lancar Lainnya</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->aset_tidak_lancar_lainnya ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="table-light fw-bold">
                            <td>Total Aset Tidak Lancar</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->total_aset_tidak_lancar ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </div>

                <div class="bg-primary text-white p-3 rounded">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">TOTAL ASET</h5>
                        <h4 class="mb-0 font-monospace">{{ number_format($report->total_aset ?? 0, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>

            <!-- KEWAJIBAN & EKUITAS -->
            <div class="col-lg-6 mb-4">
                <!-- KEWAJIBAN -->
                <h6 class="text-warning border-bottom pb-2 mb-3">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    KEWAJIBAN
                </h6>

                <!-- Kewajiban Jangka Pendek -->
                <div class="mb-4">
                    <h6 class="text-secondary mb-3">Kewajiban Jangka Pendek</h6>
                    <table class="table table-sm">
                        <tr>
                            <td>Simpanan Anggota</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->simpanan_anggota ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Simpanan Non Anggota</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->simpanan_non_anggota ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Hutang Bank</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->hutang_bank ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Hutang Lainnya</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->hutang_lainnya ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="table-light fw-bold">
                            <td>Total Kewajiban Jangka Pendek</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->total_kewajiban_jangka_pendek ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </div>

                <!-- Kewajiban Jangka Panjang -->
                <div class="mb-4">
                    <h6 class="text-secondary mb-3">Kewajiban Jangka Panjang</h6>
                    <table class="table table-sm">
                        <tr>
                            <td>Hutang Jangka Panjang</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->hutang_jangka_panjang ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Kewajiban Lainnya</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->kewajiban_lainnya ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="table-light fw-bold">
                            <td>Total Kewajiban Jangka Panjang</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->total_kewajiban_jangka_panjang ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </div>

                <div class="bg-warning text-dark p-3 rounded mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">TOTAL KEWAJIBAN</h6>
                        <h5 class="mb-0 font-monospace">{{ number_format($report->total_kewajiban ?? 0, 0, ',', '.') }}
                        </h5>
                    </div>
                </div>

                <!-- EKUITAS -->
                <h6 class="text-success border-bottom pb-2 mb-3">
                    <i class="bi bi-pie-chart me-1"></i>
                    EKUITAS
                </h6>

                <div class="mb-4">
                    <table class="table table-sm">
                        <tr>
                            <td>Simpanan Pokok</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->simpanan_pokok ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Simpanan Wajib</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->simpanan_wajib ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Cadangan</td>
                            <td class="text-end font-monospace">{{ number_format($report->cadangan ?? 0, 0, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td>SHU Belum Dibagi</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->shu_belum_dibagi ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Ekuitas Lainnya</td>
                            <td class="text-end font-monospace">
                                {{ number_format($report->ekuitas_lainnya ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </div>

                <div class="bg-success text-white p-3 rounded mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">TOTAL EKUITAS</h6>
                        <h5 class="mb-0 font-monospace">{{ number_format($report->total_ekuitas ?? 0, 0, ',', '.') }}
                        </h5>
                    </div>
                </div>

                <div class="bg-dark text-white p-3 rounded">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">TOTAL KEWAJIBAN + EKUITAS</h5>
                        <h4 class="mb-0 font-monospace">
                            {{ number_format(($report->total_kewajiban ?? 0) + ($report->total_ekuitas ?? 0), 0, ',', '.') }}
                        </h4>
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
                Total Aset: Rp {{ number_format($totalAset, 0, ',', '.') }}<br>
                Total Kewajiban + Ekuitas: Rp {{ number_format($totalKewajibanEkuitas, 0, ',', '.') }}
            </p>
    </div>
    @else
    <div class="alert alert-success mt-4">
        <h6><i class="bi bi-check-circle me-2"></i>Neraca Seimbang</h6>
        <p class="mb-0">Total Aset sama dengan Total Kewajiban + Ekuitas</p>
    </div>
    @endif

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

@push('scripts')
<script>
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
                location.reload();
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
