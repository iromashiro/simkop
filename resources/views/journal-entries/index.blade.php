@extends('layouts.app')

@section('title', 'Jurnal Umum')

@section('page-header')
<h1 class="h2 mb-0">
    <i class="bi bi-journal-text me-2"></i>
    Jurnal Umum
</h1>
<div class="btn-toolbar mb-2 mb-md-0">
    @can('create_journal_entries')
    <div class="btn-group me-2">
        <a href="{{ route('journal-entries.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>
            Buat Jurnal
        </a>
    </div>
    @endcan
    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i>
            Export
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="{{ route('journal-entries.export', ['format' => 'excel']) }}">
                    <i class="bi bi-file-earmark-excel me-2"></i>Excel
                </a></li>
            <li><a class="dropdown-item" href="{{ route('journal-entries.export', ['format' => 'pdf']) }}">
                    <i class="bi bi-file-earmark-pdf me-2"></i>PDF
                </a></li>
        </ul>
    </div>
</div>
@endsection

@section('content')
<div x-data="journalManagement()" x-init="init()">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Jurnal
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($statistics['total_entries'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-journal-text text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Disetujui
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($statistics['approved_entries'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Menunggu Persetujuan
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($statistics['pending_entries'] ?? 0) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clock text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Nilai (Bulan Ini)
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Rp {{ number_format($statistics['monthly_total'] ?? 0, 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-currency-dollar text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter & Pencarian</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('journal-entries.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" id="search" name="search"
                            value="{{ request('search') }}" placeholder="Cari nomor jurnal atau deskripsi...">
                    </div>
                </div>

                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Menunggu</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Disetujui
                        </option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Ditolak
                        </option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="date_from" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="date_from" name="date_from"
                        value="{{ request('date_from') }}">
                </div>

                <div class="col-md-2">
                    <label for="date_to" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="date_to" name="date_to"
                        value="{{ request('date_to') }}">
                </div>

                <div class="col-md-2">
                    <label for="fiscal_period_id" class="form-label">Periode Fiskal</label>
                    <select class="form-select" id="fiscal_period_id" name="fiscal_period_id">
                        <option value="">Semua Periode</option>
                        @foreach($fiscalPeriods as $period)
                        <option value="{{ $period->id }}"
                            {{ request('fiscal_period_id') == $period->id ? 'selected' : '' }}>
                            {{ $period->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Journal Entries Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Jurnal</h6>
            <div class="d-flex align-items-center">
                <span class="text-muted me-3">
                    Menampilkan {{ $journalEntries->firstItem() ?? 0 }} - {{ $journalEntries->lastItem() ?? 0 }}
                    dari {{ $journalEntries->total() }} jurnal
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No. Jurnal</th>
                            <th>Tanggal</th>
                            <th>Deskripsi</th>
                            <th>Total Debit</th>
                            <th>Total Kredit</th>
                            <th>Status</th>
                            <th>Dibuat Oleh</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($journalEntries as $entry)
                        <tr>
                            <td>
                                <span class="fw-bold text-primary">{{ $entry->entry_number }}</span>
                            </td>
                            <td>{{ $entry->entry_date->format('d M Y') }}</td>
                            <td>
                                <div class="fw-bold">{{ $entry->description }}</div>
                                @if($entry->reference_number)
                                <small class="text-muted">Ref: {{ $entry->reference_number }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="fw-bold text-success">
                                    Rp {{ number_format($entry->total_debit, 0, ',', '.') }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-info">
                                    Rp {{ number_format($entry->total_credit, 0, ',', '.') }}
                                </span>
                            </td>
                            <td>
                                @switch($entry->status)
                                @case('draft')
                                <span class="badge bg-secondary">Draft</span>
                                @break
                                @case('pending')
                                <span class="badge bg-warning">Menunggu</span>
                                @break
                                @case('approved')
                                <span class="badge bg-success">Disetujui</span>
                                @break
                                @case('rejected')
                                <span class="badge bg-danger">Ditolak</span>
                                @break
                                @default
                                <span class="badge bg-light text-dark">{{ ucfirst($entry->status) }}</span>
                                @endswitch
                            </td>
                            <td>
                                <div>{{ $entry->createdBy->name }}</div>
                                <small class="text-muted">{{ $entry->created_at->format('d M Y H:i') }}</small>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    @can('view_journal_entries')
                                    <a href="{{ route('journal-entries.show', $entry) }}"
                                        class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @endcan

                                    @can('edit_journal_entries')
                                    @if(in_array($entry->status, ['draft', 'rejected']))
                                    <a href="{{ route('journal-entries.edit', $entry) }}"
                                        class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @endif
                                    @endcan

                                    @can('approve_journal_entries')
                                    @if($entry->status === 'pending')
                                    <button type="button" class="btn btn-sm btn-outline-success" title="Setujui"
                                        @click="approveEntry({{ $entry->id }})">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Tolak"
                                        @click="rejectEntry({{ $entry->id }})">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                    @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-inbox display-4"></i>
                                    <p class="mt-2">Tidak ada jurnal ditemukan</p>
                                    @can('create_journal_entries')
                                    <a href="{{ route('journal-entries.create') }}" class="btn btn-primary">
                                        <i class="bi bi-plus-circle me-1"></i>
                                        Buat Jurnal Pertama
                                    </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($journalEntries->hasPages())
        <div class="card-footer">
            {{ $journalEntries->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    function journalManagement() {
    return {
        loading: false,

        init() {
            // Initialize any required functionality
        },

        async approveEntry(entryId) {
            if (!confirm('Apakah Anda yakin ingin menyetujui jurnal ini?')) {
                return;
            }

            this.loading = true;

            try {
                const response = await fetch(`/journal-entries/${entryId}/approve`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    HERMES.utils.showToast('Jurnal berhasil disetujui', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    const error = await response.json();
                    HERMES.utils.showToast(error.message || 'Gagal menyetujui jurnal', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                HERMES.utils.showToast('Terjadi kesalahan sistem', 'error');
            } finally {
                this.loading = false;
            }
        },

        async rejectEntry(entryId) {
            const reason = prompt('Masukkan alasan penolakan:');
            if (!reason) {
                return;
            }

            this.loading = true;

            try {
                const response = await fetch(`/journal-entries/${entryId}/reject`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ reason })
                });

                if (response.ok) {
                    HERMES.utils.showToast('Jurnal berhasil ditolak', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    const error = await response.json();
                    HERMES.utils.showToast(error.message || 'Gagal menolak jurnal', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                HERMES.utils.showToast('Terjadi kesalahan sistem', 'error');
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endpush
@endsection
