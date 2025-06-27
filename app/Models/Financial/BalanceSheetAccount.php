<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use App\Traits\BelongsToCooperative;

class BalanceSheetAccount extends Model
{
    use HasFactory, HasAuditLog, BelongsToCooperative;

    protected $fillable = [
        'cooperative_id',
        'reporting_year',
        'account_code',
        'account_name',
        'account_category',
        'account_subcategory',
        'current_year_amount',
        'previous_year_amount',
        'note_reference',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'current_year_amount' => 'decimal:2',
            'previous_year_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    // Scopes
    public function scopeByYear($query, int $year)
    {
        return $query->where('reporting_year', $year);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('account_category', $category);
    }

    public function scopeAssets($query)
    {
        return $query->where('account_category', 'asset');
    }

    public function scopeLiabilities($query)
    {
        return $query->where('account_category', 'liability');
    }

    public function scopeEquity($query)
    {
        return $query->where('account_category', 'equity');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('account_code');
    }

    // Helper methods
    public function isAsset(): bool
    {
        return $this->account_category === 'asset';
    }

    public function isLiability(): bool
    {
        return $this->account_category === 'liability';
    }

    public function isEquity(): bool
    {
        return $this->account_category === 'equity';
    }

    public function getVariance(): float
    {
        return $this->current_year_amount - $this->previous_year_amount;
    }

    public function getVariancePercentage(): float
    {
        if ($this->previous_year_amount == 0) {
            return $this->current_year_amount > 0 ? 100 : 0;
        }

        return (($this->current_year_amount - $this->previous_year_amount) / $this->previous_year_amount) * 100;
    }

    public function getCategoryLabel(): string
    {
        return match ($this->account_category) {
            'asset' => 'Aset',
            'liability' => 'Liabilitas',
            'equity' => 'Ekuitas',
            default => $this->account_category,
        };
    }

    public function getSubcategoryLabel(): string
    {
        return match ($this->account_subcategory) {
            'current_asset' => 'Aset Lancar',
            'fixed_asset' => 'Aset Tetap',
            'other_asset' => 'Aset Lainnya',
            'current_liability' => 'Liabilitas Jangka Pendek',
            'long_term_liability' => 'Liabilitas Jangka Panjang',
            'member_equity' => 'Ekuitas Anggota',
            'retained_earnings' => 'Sisa Hasil Usaha',
            'other_equity' => 'Ekuitas Lainnya',
            default => $this->account_subcategory,
        };
    }
}
