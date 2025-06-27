<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use App\Traits\BelongsToCooperative;

class EquityChange extends Model
{
    use HasFactory, HasAuditLog, BelongsToCooperative;

    protected $fillable = [
        'cooperative_id',
        'reporting_year',
        'transaction_description',
        'simpanan_pokok',
        'simpanan_wajib',
        'cadangan_umum',
        'cadangan_risiko',
        'sisa_hasil_usaha',
        'ekuitas_lain',
        'transaction_type',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'simpanan_pokok' => 'decimal:2',
            'simpanan_wajib' => 'decimal:2',
            'cadangan_umum' => 'decimal:2',
            'cadangan_risiko' => 'decimal:2',
            'sisa_hasil_usaha' => 'decimal:2',
            'ekuitas_lain' => 'decimal:2',
            'jumlah_ekuitas' => 'decimal:2', // ✅ ADDED: Cast for computed field
            'sort_order' => 'integer',
        ];
    }

    // Scopes
    public function scopeByYear($query, int $year)
    {
        return $query->where('reporting_year', $year);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeOpeningBalance($query)
    {
        return $query->where('transaction_type', 'opening_balance');
    }

    public function scopeTransactions($query)
    {
        return $query->where('transaction_type', 'transaction');
    }

    public function scopeClosingBalance($query)
    {
        return $query->where('transaction_type', 'closing_balance');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // Helper methods
    public function isOpeningBalance(): bool
    {
        return $this->transaction_type === 'opening_balance';
    }

    public function isTransaction(): bool
    {
        return $this->transaction_type === 'transaction';
    }

    public function isClosingBalance(): bool
    {
        return $this->transaction_type === 'closing_balance';
    }

    // ✅ FIXED: Use accessor instead of computed column
    public function getTotalEquity(): float
    {
        return $this->jumlah_ekuitas ?? ($this->simpanan_pokok + $this->simpanan_wajib + $this->cadangan_umum +
            $this->cadangan_risiko + $this->sisa_hasil_usaha + $this->ekuitas_lain);
    }

    public function getTypeLabel(): string
    {
        return match ($this->transaction_type) {
            'opening_balance' => 'Saldo Awal',
            'transaction' => 'Mutasi',
            'closing_balance' => 'Saldo Akhir',
            default => $this->transaction_type,
        };
    }
}
