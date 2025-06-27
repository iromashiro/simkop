<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use App\Traits\BelongsToCooperative;

class BudgetPlan extends Model
{
    use HasFactory, HasAuditLog, BelongsToCooperative;

    protected $fillable = [
        'cooperative_id',
        'budget_year',
        'budget_category',
        'budget_item',
        'planned_amount',
        'comparison_amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'planned_amount' => 'decimal:2',
            'comparison_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    // Scopes
    public function scopeByYear($query, int $year)
    {
        return $query->where('budget_year', $year);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('budget_category', $category);
    }

    public function scopeModalTersedia($query)
    {
        return $query->where('budget_category', 'modal_tersedia');
    }

    public function scopeRencanaPendapatan($query)
    {
        return $query->where('budget_category', 'rencana_pendapatan');
    }

    public function scopeRencanaBiaya($query)
    {
        return $query->where('budget_category', 'rencana_biaya');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('budget_item');
    }

    // Helper methods
    public function getVariance(): float
    {
        return $this->planned_amount - $this->comparison_amount;
    }

    public function getVariancePercentage(): float
    {
        if ($this->comparison_amount == 0) {
            return $this->planned_amount > 0 ? 100 : 0;
        }

        return (($this->planned_amount - $this->comparison_amount) / $this->comparison_amount) * 100;
    }

    public function getCategoryLabel(): string
    {
        return match ($this->budget_category) {
            'modal_tersedia' => 'Modal Tersedia',
            'rencana_pendapatan' => 'Rencana Pendapatan',
            'rencana_biaya' => 'Rencana Biaya',
            default => $this->budget_category,
        };
    }

    public function getVarianceClass(): string
    {
        $variance = $this->getVariancePercentage();

        if ($this->budget_category === 'rencana_biaya') {
            // For expenses, lower is better
            return $variance < 0 ? 'text-success' : 'text-danger';
        } else {
            // For income/capital, higher is better
            return $variance > 0 ? 'text-success' : 'text-danger';
        }
    }
}
