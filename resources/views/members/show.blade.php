@extends('layouts.app')

@section('title', 'Detail Anggota - ' . $member->full_name)

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-person me-2"></i>
    Detail Anggota
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    <div class="btn-group me-2">
        <a href="{{ route('members.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>
            Kembali
        </a>
    </div>
    @can('edit_members')
    <div class="btn-group me-2">
        <a href="{{ route('members.edit', $member) }}" class="btn btn-warning">
            <i class="bi bi-pencil me-1"></i>
            Edit
        </a>
    </div>
    @endcan
    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-three-dots me-1"></i>
            Aksi
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#">
                    <i class="bi bi-printer me-2"></i>Cetak Kartu Anggota
                </a></li>
            <li><a class="dropdown-item" href="#">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Export PDF
                </a></li>
            <li>
                <hr class="dropdown-divider">
            </li>
            @can('delete_members')
            <li><a class="dropdown-item text-danger" href="#" onclick="deleteMember()">
                    <i class="bi bi-trash me-2"></i>Hapus Anggota
                </a></li>
            @endcan
        </ul>
    </div>
</div>
@endsection

@section('content')
<div class="row">
    <!-- Member Profile -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-body text-center">
                <div class="mb-3">
                    <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold"
                        style="width: 80px; height: 80px; font-size: 2rem;">
                        {{ strtoupper(substr($member->full_name, 0, 2)) }}
                    </div>
                </div>
                <h4 class="card-title">{{ $member->full_name }}</h4>
                <p class="text-muted">{{ $member->member_number }}</p>

                <div class="mb-3">
                    @switch($member->status)
                    @case('active')
                    <span class="badge bg-success fs-6">Aktif</span>
                    @break
                    @case('pending')
                    <span class="badge bg-warning fs-6">Menunggu Persetujuan</span>
                    @break
                    @case('inactive')
                    <span class="badge bg-secondary fs-6">Tidak Aktif</span>
                    @break
                    @case('suspended')
                    <span class="badge bg-danger fs-6">Ditangguhkan</span>
                    @break
                    @default
                    <span class="badge bg-light text-dark fs-6">{{ ucfirst($member->status) }}</span>
                    @endswitch
                </div>

                <div class="row text-center">
                    <div class="col-4">
                        <div class="border-end">
                            <h5 class="mb-0">{{ $member->savingsAccounts->count() }}</h5>
                            <small class="text-muted">Rekening Simpanan</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border-end">
                            <h5 class="mb-0">{{ $member->loanAccounts->count() }}</h5>
                            <small class="text-muted">Pinjaman</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <h5 class="mb-0">{{ $member->join_date->diffInYears(now()) }}</h5>
                        <small class="text-muted">Tahun Bergabung</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card shadow mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Aksi Cepat</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    @can('create_savings')
                    <a href="{{ route('savings.create', ['member_id' => $member->id]) }}"
                        class="btn btn-outline-success">
                        <i class="bi bi-piggy-bank me-2"></i>
                        Buka Rekening Simpanan
                    </a>
                    @endcan

                    @can('create_loans')
                    <a href="{{ route('loans.create', ['member_id' => $member->id]) }}" class="btn btn-outline-info">
                        <i class="bi bi-cash-stack me-2"></i>
                        Ajukan Pinjaman
                    </a>
                    @endcan

                    <a href="#" class="btn btn-outline-primary">
                        <i class="bi bi-graph-up me-2"></i>
                        Lihat Laporan Keuangan
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Member Details -->
    <div class="col-lg-8">
        <!-- Personal Information -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-person-badge me-2"></i>
                    Informasi Pribadi
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Nama Lengkap</label>
                        <p class="fw-bold">{{ $member->full_name }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Nomor Anggota</label>
                        <p class="fw-bold">{{ $member->member_number }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Nomor KTP</label>
                        <p class="fw-bold">{{ $member->id_number }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Tanggal Lahir</label>
                        <p class="fw-bold">
                            {{ $member->birth_date ? $member->birth_date->format('d M Y') : '-' }}
                            @if($member->birth_date)
                            <small class="text-muted">({{ $member->birth_date->age }} tahun)</small>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Tanggal Bergabung</label>
                        <p class="fw-bold">{{ $member->join_date->format('d M Y') }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <p>
                            @switch($member->status)
                            @case('active')
                            <span class="badge bg-success">Aktif</span>
                            @break
                            @case('pending')
                            <span class="badge bg-warning">Menunggu Persetujuan</span>
                            @break
                            @case('inactive')
                            <span class="badge bg-secondary">Tidak Aktif</span>
                            @break
                            @case('suspended')
                            <span class="badge bg-danger">Ditangguhkan</span>
                            @break
                            @default
                            <span class="badge bg-light text-dark">{{ ucfirst($member->status) }}</span>
                            @endswitch
                        </p>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Alamat</label>
                        <p class="fw-bold">{{ $member->address }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-telephone me-2"></i>
                    Informasi Kontak
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Email</label>
                        <p class="fw-bold">
                            <a href="mailto:{{ $member->email }}" class="text-decoration-none">
                                {{ $member->email }}
                            </a>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Nomor Telepon</label>
                        <p class="fw-bold">
                            <a href="tel:{{ $member->phone }}" class="text-decoration-none">
                                {{ $member->phone }}
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-graph-up me-2"></i>
                    Ringkasan Keuangan
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="text-success mb-1">
                                Rp {{ number_format($statistics['total_savings'] ?? 0, 0, ',', '.') }}
                            </h4>
                            <small class="text-muted">Total Simpanan</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="text-info mb-1">
                                Rp {{ number_format($statistics['total_loans'] ?? 0, 0, ',', '.') }}
                            </h4>
                            <small class="text-muted">Total Pinjaman</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="text-warning mb-1">
                                Rp {{ number_format($statistics['outstanding_loans'] ?? 0, 0, ',', '.') }}
                            </h4>
                            <small class="text-muted">Sisa Pinjaman</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    Transaksi Terbaru
                </h6>
                <a href="#" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <!-- Sample transactions - replace with actual data -->
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Setoran Simpanan</h6>
                            <p class="timeline-text">Rp 500.000</p>
                            <small class="text-muted">2 hari yang lalu</small>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Pembayaran Pinjaman</h6>
                            <p class="timeline-text">Rp 1.000.000</p>
                            <small class="text-muted">1 minggu yang lalu</small>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-warning"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">Pinjaman Disetujui</h6>
                            <p class="timeline-text">Rp 10.000.000</p>
                            <small class="text-muted">2 minggu yang lalu</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function deleteMember() {
    if (confirm('Apakah Anda yakin ingin menghapus anggota "{{ $member->full_name }}"?\n\nTindakan ini tidak dapat dibatalkan.')) {
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route('members.destroy', $member) }}';

        const methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        methodField.value = 'DELETE';

        const tokenField = document.createElement('input');
        tokenField.type = 'hidden';
        tokenField.name = '_token';
        tokenField.value = '{{ csrf_token() }}';

        form.appendChild(methodField);
        form.appendChild(tokenField);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
@endpush

@push('styles')
<style>
    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 30px;
    }

    .timeline-marker {
        position: absolute;
        left: -22px;
        top: 0;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px #dee2e6;
    }

    .timeline-content {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 3px solid #0d6efd;
    }

    .timeline-title {
        margin-bottom: 5px;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .timeline-text {
        margin-bottom: 5px;
        font-weight: 500;
    }
</style>
@endpush
@endsection
