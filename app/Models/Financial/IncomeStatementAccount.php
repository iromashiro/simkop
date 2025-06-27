<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use App\Traits\BelongsToCooperative;

class IncomeStatementAccount extends Model
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

    public function scopeRevenue($query)
    {
        return $query->where('account_category', 'revenue');
    }

    public function scopeExpense($query)
    {
        return $query->where('account_category', 'expense');
    }

    public function scopeOtherIncome($query)
    {
        return $query->where('account_category', 'other_income');
    }

    public function scopeOtherExpense($query)
    {
        return $query->where('account_category', 'other_expense');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('account_code');
    }

    // Helper methods
    public function isRevenue(): bool
    {
        return $this->account_category === 'revenue';
    }

    public function isExpense(): bool
    {
        return $this->account_category === 'expense';
    }

    public function isOtherIncome(): bool
    {
        return $this->account_category === 'other_income';
    }

    public function isOtherExpense(): bool
    {
        return $this->account_category === 'other_expense';
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
            'revenue' => 'Pendapatan',
            'expense' => 'Beban',
            'other_income' => 'Pendapatan Lain-lain',
            'other_expense' => 'Beban Lain-lain',
            default => $this->account_category,
        };
    }

    public function getSubcategoryLabel(): string
    {
        return match ($this->account_subcategory) {
            'member_participation' => 'Partisipasi Anggota',
            'non_member_participation' => 'Partisipasi Non-Anggota',
            'operating_expense' => 'Beban Operasional',
            'administrative_expense' => 'Beban Administrasi',
            'financial_expense' => 'Beban Keuangan',
            'other_operating_income' => 'Pendapatan Operasional Lainnya',
            'extraordinary_income' => 'Pendapatan Luar Biasa',
            'other_operating_expense' => 'Beban Operasional Lainnya',
            'extraordinary_expense' => 'Beban Luar Biasa',
            default => $this->account_subcategory,
        };
    }
}
