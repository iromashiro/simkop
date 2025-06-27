<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use App\Traits\BelongsToCooperative;

class MemberReceivable extends Model
{
    use HasFactory, HasAuditLog, BelongsToCooperative;

    protected $fillable = [
        'cooperative_id',
        'member_name',
        'member_number',
        'reporting_year',
        'receivable_amount',
        'loan_date',
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'receivable_amount' => 'decimal:2',
            'loan_date' => 'date',
            'due_date' => 'date',
        ];
    }

    // Scopes
    public function scopeByYear($query, int $year)
    {
        return $query->where('reporting_year', $year);
    }

    public function scopeByMember($query, string $memberName)
    {
        return $query->where('member_name', 'like', "%{$memberName}%");
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCurrent($query)
    {
        return $query->where('status', 'current');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopeRestructured($query)
    {
        return $query->where('status', 'restructured');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('member_name');
    }

    // Helper methods
    public function isCurrent(): bool
    {
        return $this->status === 'current';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue';
    }

    public function isRestructured(): bool
    {
        return $this->status === 'restructured';
    }

    public function getDaysOverdue(): int
    {
        if (!$this->due_date || $this->isCurrent()) {
            return 0;
        }

        return max(0, now()->diffInDays($this->due_date, false));
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'current' => 'Lancar',
            'overdue' => 'Menunggak',
            'restructured' => 'Restrukturisasi',
            default => $this->status,
        };
    }

    public function getStatusClass(): string
    {
        return match ($this->status) {
            'current' => 'badge-success',
            'overdue' => 'badge-danger',
            'restructured' => 'badge-warning',
            default => 'badge-secondary',
        };
    }

    public function getMemberDisplayName(): string
    {
        return $this->member_number ? "{$this->member_name} ({$this->member_number})" : $this->member_name;
    }
}
