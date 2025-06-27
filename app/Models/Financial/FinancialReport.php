<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use App\Traits\BelongsToCooperative;
use App\Models\User;

class FinancialReport extends Model
{
    use HasFactory, HasAuditLog, BelongsToCooperative;

    protected $fillable = [
        'cooperative_id',
        'report_type',
        'reporting_year',
        'reporting_period',
        'status',
        'data',
        'notes',
        'rejection_reason',
        'created_by',
        'approved_by',
        'submitted_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'reporting_period' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('report_type', $type);
    }

    public function scopeByYear($query, int $year)
    {
        return $query->where('reporting_year', $year);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Helper methods
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'rejected']);
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'submitted';
    }

    public function submit(): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function approve(int $approvedBy): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function reject(int $approvedBy, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'submitted' => 'Diajukan',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => $this->status,
        };
    }

    public function getStatusClass(): string
    {
        return match ($this->status) {
            'draft' => 'badge-secondary',
            'submitted' => 'badge-warning',
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    public function getReportTypeLabel(): string
    {
        return match ($this->report_type) {
            'balance_sheet' => 'Laporan Posisi Keuangan',
            'income_statement' => 'Laporan Perhitungan Hasil Usaha',
            'equity_changes' => 'Laporan Perubahan Ekuitas',
            'cash_flow' => 'Laporan Arus Kas',
            'member_savings' => 'Daftar Simpanan Anggota',
            'member_receivables' => 'Daftar Piutang Simpan Pinjam Anggota',
            'npl_receivables' => 'Daftar Piutang Tidak Lancar',
            'shu_distribution' => 'Daftar Rencana Pembagian SHU',
            'budget_plan' => 'Rencana Anggaran Pendapatan & Belanja',
            'notes_to_financial' => 'Catatan Atas Laporan Keuangan',
            default => $this->report_type,
        };
    }
}
