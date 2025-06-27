<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use App\Traits\BelongsToCooperative;

class MemberSaving extends Model
{
    use HasFactory, HasAuditLog, BelongsToCooperative;

    protected $fillable = [
        'cooperative_id',
        'member_name',
        'member_number',
        'reporting_year',
        'simpanan_pokok',
        'simpanan_wajib',
        'simpanan_khusus',
        'simpanan_sukarela',
    ];

    protected function casts(): array
    {
        return [
            'simpanan_pokok' => 'decimal:2',
            'simpanan_wajib' => 'decimal:2',
            'simpanan_khusus' => 'decimal:2',
            'simpanan_sukarela' => 'decimal:2',
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
    public function getTotalSimpanan(): float
    {
        return $this->simpanan_pokok + $this->simpanan_wajib + $this->simpanan_khusus + $this->simpanan_sukarela;
    }

    public function getMemberDisplayName(): string
    {
        return $this->member_number ? "{$this->member_name} ({$this->member_number})" : $this->member_name;
    }
}
