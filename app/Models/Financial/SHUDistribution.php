<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use App\Traits\BelongsToCooperative;

class SHUDistribution extends Model
{
    use HasFactory, HasAuditLog, BelongsToCooperative;

    protected $table = 'shu_distribution';

    protected $fillable = [
        'cooperative_id',
        'member_name',
        'member_number',
        'reporting_year',
        'jasa_simpanan',
        'jasa_pinjaman',
        'simpanan_participation',
        'pinjaman_participation',
    ];

    protected function casts(): array
    {
        return [
            'jasa_simpanan' => 'decimal:2',
            'jasa_pinjaman' => 'decimal:2',
            'simpanan_participation' => 'decimal:2',
            'pinjaman_participation' => 'decimal:2',
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('member_name');
    }

    // Helper methods
    public function getTotalSHU(): float
    {
        return $this->jasa_simpanan + $this->jasa_pinjaman;
    }

    public function getMemberDisplayName(): string
    {
        return $this->member_number ? "{$this->member_name} ({$this->member_number})" : $this->member_name;
    }

    public function getSimpananPercentage(float $totalSimpananParticipation): float
    {
        return $totalSimpananParticipation > 0 ? ($this->simpanan_participation / $totalSimpananParticipation) * 100 : 0;
    }

    public function getPinjamanPercentage(float $totalPinjamanParticipation): float
    {
        return $totalPinjamanParticipation > 0 ? ($this->pinjaman_participation / $totalPinjamanParticipation) * 100 : 0;
    }
}
