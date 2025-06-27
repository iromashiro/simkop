@extends('layouts.app')

@section('title', 'Detail Koperasi - ' . $cooperative->name)

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-building me-2"></i>
    Detail Koperasi
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    @can('manage_cooperative')
    <div class="btn-group me-2">
        <a href="{{ route('cooperative.edit', $cooperative) }}" class="btn btn-warning">
            <i class="bi bi-pencil me-1"></i>
            Edit
        </a>
    </div>
    <div class="btn-group me-2">
        <a href="{{ route('cooperative.settings', $cooperative) }}" class="btn btn-outline-secondary">
            <i class="bi bi-gear me-1"></i>
            Pengaturan
        </a>
    </div>
    @endcan
    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i>
            Export
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Profil Koperasi (PDF)
                </a></li>
            <li><a class="dropdown-item" href="#">
                    <i class="bi bi-file-earmark-excel me-2"></i>Data Anggota (Excel)
                </a></li>
        </ul>
    </div>
</div>
@endsection

@section('content')
<div class="row">
    <!-- Cooperative Profile -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-body text-center">
                <div class="mb-3">
                    <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold"
                        style="width: 80px; height: 80px; font-size: 2rem;">
                        {{ strtoupper(substr($cooperative->name, 0, 2)) }}
                    </div>
                </div>
                <h4 class="card-title">{{ $cooperative->name }}</h4>
                <p class="text-muted">{{ $cooperative->registration_number }}</p>

                <div class="mb-3">
                    @if($cooperative->is_active)
                    <span class="badge bg-success fs-6">Aktif</span>
                    @else
                    <span class="badge bg-secondary fs-6">Tidak Aktif</span>
                    @endif
                </div>

                <div class="row text-center">
                    <div class="col-4">
                        <div class="border-end">
                            <h5 class="mb-0">{{ number_format($statistics['total_members'] ?? 0) }}</h5>
                            <small class="text-muted">Anggota</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border-end">
                            <h5 class="mb-0">{{ number_format($statistics['total_users'] ?? 0) }}</h5>
                            <small class="text-muted">Pengguna</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <h5 class="mb-0">{{ $cooperative->established_date->diffInYears(now()) }}</h5>
                        <small class="text-muted">Tahun Berdiri</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="card shadow mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Ringkasan Keuangan</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Total Aset</span>
                        <span class="fw-bold text-success">
                            Rp {{ number_format($statistics['total_assets'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Total Simpanan</span>
                        <span class="fw-bold text-info">
                            Rp {{ number_format($statistics['total_savings'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Total Pinjaman</span>
                        <span class="fw-bold text-warning">
                            Rp {{ number_format($statistics['total_loans'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">SHU Tahun Ini</span>
                    <span class="fw-bold text-primary">
                        Rp {{ number_format($statistics['shu_current_year'] ?? 0, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Cooperative Details -->
    <div class="col-lg-8">
        <!-- Basic Information -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-building me-2"></i>
                    Informasi Dasar
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Nama Koperasi</label>
                        <p class="fw-bold">{{ $cooperative->name }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Nomor Registrasi</label>
                        <p class="fw-bold">{{ $cooperative->registration_number }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Status Hukum</label>
                        <p class="fw-bold">{{ $cooperative->legal_status }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Jenis Usaha</label>
                        <p class="fw-bold">{{ $cooperative->business_type }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Tanggal Berdiri</label>
                        <p class="fw-bold">{{ $cooperative->established_date->format('d M Y') }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <p>
                            @if($cooperative->is_active)
                            <span class="badge bg-success">Aktif</span>
                            @else
                            <span class="badge bg-secondary">Tidak Aktif</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Alamat</label>
                        <p class="fw-bold">{{ $cooperative->address }}</p>
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
                            <a href="mailto:{{ $cooperative->email }}" class="text-decoration-none">
                                {{ $cooperative->email }}
                            </a>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Nomor Telepon</label>
                        <p class="fw-bold">
                            <a href="tel:{{ $cooperative->phone }}" class="text-decoration-none">
                                {{ $cooperative->phone }}
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Dashboard -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-graph-up me-2"></i>
                    Statistik Koperasi
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="text-center p-3 bg-primary bg-opacity-10 rounded">
                            <h4 class="text-primary mb-1">{{ number_format($statistics['total_members'] ?? 0) }}</h4>
                            <small class="text-muted">Total Anggota</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-center p-3 bg-success bg-opacity-10 rounded">
                            <h4 class="text-success mb-1">{{ number_format($statistics['active_members'] ?? 0) }}</h4>
                            <small class="text-muted">Anggota Aktif</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-center p-3 bg-info bg-opacity-10 rounded">
                            <h4 class="text-info mb-1">{{ number_format($statistics['total_savings_accounts'] ?? 0) }}
                            </h4>
                            <small class="text-muted">Rekening Simpanan</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-center p-3 bg-warning bg-opacity-10 rounded">
                            <h4 class="text-warning mb-1">{{ number_format($statistics['active_loans'] ?? 0) }}</h4>
                            <small class="text-muted">Pinjaman Aktif</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    Aktivitas Terbaru
                </h6>
                <a href="#" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <!-- Sample activities - replace with actual data -->
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-success rounded-circle d-flex align-items-center justify-content-center"
                                    style="width: 40px; height: 40px;">
                                    <i class="bi bi-person-plus text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">Anggota baru bergabung</h6>
                                <p class="mb-1 text-muted">John Doe telah menjadi anggota koperasi</p>
                                <small class="text-muted">2 jam yang lalu</small>
                            </div>
                        </div>
                    </div>

                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-info rounded-circle d-flex align-items-center justify-content-center"
                                    style="width: 40px; height: 40px;">
                                    <i class="bi bi-cash-stack text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">Pinjaman disetujui</h6>
                                <p class="mb-1 text-muted">Pinjaman sebesar Rp 10.000.000 telah disetujui</p>
                                <small class="text-muted">5 jam yang lalu</small>
                            </div>
                        </div>
                    </div>

                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center"
                                    style="width: 40px; height: 40px;">
                                    <i class="bi bi-piggy-bank text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">Setoran simpanan</h6>
                                <p class="mb-1 text-muted">Total setoran hari ini: Rp 25.000.000</p>
                                <small class="text-muted">1 hari yang lalu</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
