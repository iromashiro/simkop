<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use App\Traits\BelongsToCooperative;

class CashFlowActivity extends Model
{
    use HasFactory, HasAuditLog, BelongsToCooperative;

    protected $fillable = [
        'cooperative_id',
        'reporting_year',
        'activity_category',
        'activity_description',
        'current_year_amount',
        'previous_year_amount',
        'is_subtotal',
        'is_total',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'current_year_amount' => 'decimal:2',
            'previous_year_amount' => 'decimal:2',
            'is_subtotal' => 'boolean',
            'is_total' => 'boolean',
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
        return $query->where('activity_category', $category);
    }

    public function scopeOperating($query)
    {
        return $query->where('activity_category', 'operating');
    }

    public function scopeInvesting($query)
    {
        return $query->where('activity_category', 'investing');
    }

    public function scopeFinancing($query)
    {
        return $query->where('activity_category', 'financing');
    }

    public function scopeSubtotals($query)
    {
        return $query->where('is_subtotal', true);
    }

    public function scopeTotals($query)
    {
        return $query->where('is_total', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // Helper methods
    public function isOperating(): bool
    {
        return $this->activity_category === 'operating';
    }

    public function isInvesting(): bool
    {
        return $this->activity_category === 'investing';
    }

    public function isFinancing(): bool
    {
        return $this->activity_category === 'financing';
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
        return match ($this->activity_category) {
            'operating' => 'Aktivitas Operasi',
            'investing' => 'Aktivitas Investasi',
            'financing' => 'Aktivitas Pendanaan',
            default => $this->activity_category,
        };
    }
}
