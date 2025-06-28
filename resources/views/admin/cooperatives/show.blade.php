{{-- resources/views/admin/cooperatives/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Detail Koperasi')

@php
$header = [
'title' => $cooperative->name,
'subtitle' => 'Detail informasi koperasi',
'actions' => '<a href="' . route('admin.cooperatives.edit', $cooperative) . '" class="btn btn-primary">
    <i class="bi bi-pencil me-1"></i> Edit Koperasi
</a>'
];

$breadcrumbs = [
['title' => 'Dashboard', 'url' => route('dashboard')],
['title' => 'Kelola Koperasi', 'url' => route('admin.cooperatives.index')],
['title' => $cooperative->name, 'url' => route('admin.cooperatives.show', $cooperative)]
];
@endphp

@section('content')
<div class="row">
    <!-- Cooperative Information -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-building me-2"></i>
                    Informasi Koperasi
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Nama Koperasi</label>
                        <div class="fw-bold fs-5">{{ $cooperative->name }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Kode Koperasi</label>
                        <div class="fw-bold fs-5 font-monospace">{{ $cooperative->code }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Nomor Registrasi</label>
                        <div class="fw-bold">{{ $cooperative->registration_number }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Tanggal Registrasi</label>
                        <div class="fw-bold">{{ $cooperative->registration_date->format('d F Y') }}</div>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Alamat</label>
                        <div class="fw-bold">{{ $cooperative->address }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Telepon</label>
                        <div class="fw-bold">{{ $cooperative->phone ?? 'Belum diatur' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Email</label>
                        <div class="fw-bold">{{ $cooperative->email ?? 'Belum diatur' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Ketua</label>
                        <div class="fw-bold">{{ $cooperative->chairman_name ?? 'Belum diatur' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Telepon Ketua</label>
                        <div class="fw-bold">{{ $cooperative->chairman_phone ?? 'Belum diatur' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Jenis Koperasi</label>
                        <div>
                            <span class="badge bg-secondary fs-6">
                                {{ ucwords(str_replace('_', ' ', $cooperative->type ?? 'Belum diatur')) }}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <div>
                            @switch($cooperative->status)
                            @case('active')
                            <span class="badge bg-success fs-6">Aktif</span>
                            @break
                            @case('inactive')
                            <span class="badge bg-danger fs-6">Tidak Aktif</span>
                            @break
                            @case('pending')
                            <span class="badge bg-warning fs-6">Pending</span>
                            @break
                            @default
                            <span class="badge bg-secondary fs-6">Tidak Diketahui</span>
                            @endswitch
                        </div>
                    </div>
                    @if($cooperative->description)
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Deskripsi</label>
                        <div class="fw-bold">{{ $cooperative->description }}</div>
                    </div>
                    @endif
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Dibuat</label>
                        <div class="fw-bold">{{ $cooperative->created_at->format('d F Y H:i') }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Terakhir Update</label>
                        <div class="fw-bold">{{ $cooperative->updated_at->format('d F Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-graph-up me-2"></i>
                    Statistik
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="bg-primary text-white rounded p-3">
                            <h3 class="mb-1">{{ $cooperative->users->count() }}</h3>
                            <small>Pengguna</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="bg-success text-white rounded p-3">
                            <h3 class="mb-1">{{ $cooperative->financialReports->count() }}</h3>
                            <small>Laporan</small>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="d-grid gap-2 mt-3">
                    <a href="{{ route('admin.cooperatives.edit', $cooperative) }}" class="btn btn-primary">
                        <i class="bi bi-pencil me-1"></i> Edit Koperasi
                    </a>
                    <a href="{{ route('admin.users.index', ['cooperative' => $cooperative->id]) }}"
                        class="btn btn-outline-primary">
                        <i class="bi bi-people me-1"></i> Lihat Pengguna
                    </a>
                    <a href="{{ route('admin.reports.approval', ['cooperative' => $cooperative->id]) }}"
                        class="btn btn-outline-success">
                        <i class="bi bi-file-earmark-text me-1"></i> Lihat Laporan
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-people me-2"></i>
                    Pengguna Koperasi ({{ $cooperative->users->count() }})
                </h5>
                <a href="{{ route('admin.users.create', ['cooperative_id' => $cooperative->id]) }}"
                    class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Pengguna
                </a>
            </div>
            <div class="card-body">
                @if($cooperative->users->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Bergabung</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cooperative->users as $user)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3"
                                            style="width: 35px; height: 35px;">
                                            {{ strtoupper(substr($user->name, 0, 2)) }}
                                        </div>
                                        <div class="fw-bold">{{ $user->name }}</div>
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @foreach($user->roles as $role)
                                    <span class="badge bg-info">{{ $role->name }}</span>
                                    @endforeach
                                </td>
                                <td>{{ $user->created_at->format('d/m/Y') }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-primary"
                                            title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4">
                    <i class="bi bi-people fs-1 text-muted"></i>
                    <p class="text-muted mt-2">Belum ada pengguna untuk koperasi ini</p>
                    <a href="{{ route('admin.users.create', ['cooperative_id' => $cooperative->id]) }}"
                        class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Pengguna Pertama
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Financial Reports -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Laporan Keuangan ({{ $cooperative->financialReports->count() }})
                </h5>
            </div>
            <div class="card-body">
                @if($cooperative->financialReports->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Jenis Laporan</th>
                                <th>Tahun</th>
                                <th>Status</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cooperative->financialReports->take(10) as $report)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ ucwords(str_replace('_', ' ', $report->report_type)) }}
                                    </div>
                                </td>
                                <td>{{ $report->reporting_year }}</td>
                                <td>
                                    @switch($report->status)
                                    @case('draft')
                                    <span class="badge bg-secondary">Draft</span>
                                    @break
                                    @case('submitted')
                                    <span class="badge bg-warning">Terkirim</span>
                                    @break
                                    @case('approved')
                                    <span class="badge bg-success">Disetujui</span>
                                    @break
                                    @case('rejected')
                                    <span class="badge bg-danger">Ditolak</span>
                                    @break
                                    @endswitch
                                </td>
                                <td>{{ $report->created_at->format('d/m/Y') }}</td>
                                <td>
                                    <a href="{{ route('admin.reports.show', $report) }}"
                                        class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i> Lihat
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($cooperative->financialReports->count() > 10)
                <div class="text-center mt-3">
                    <a href="{{ route('admin.reports.approval', ['cooperative' => $cooperative->id]) }}"
                        class="btn btn-outline-primary">
                        Lihat Semua Laporan ({{ $cooperative->financialReports->count() }})
                    </a>
                </div>
                @endif
                @else
                <div class="text-center py-4">
                    <i class="bi bi-file-earmark-text fs-1 text-muted"></i>
                    <p class="text-muted mt-2">Belum ada laporan keuangan</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
