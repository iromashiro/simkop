<?php
// app/Domain/Analytics/Models/KPIMetric.php
namespace App\Domain\Analytics\Models;

use App\Infrastructure\Tenancy\TenantModel;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KPIMetric extends TenantModel
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'cooperative_id',
        'metric_name',
        'metric_type',
        'current_value',
        'previous_value',
        'target_value',
        'unit',
        'period_type',
        'period_start',
        'period_end',
        'calculation_method',
        'data_source',
        'metadata',
        'calculated_at',
    ];

    protected $casts = [
        'current_value' => 'decimal:2',
        'previous_value' => 'decimal:2',
        'target_value' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'metadata' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Cooperative\Models\Cooperative::class);
    }

    public function getVarianceAttribute(): float
    {
        if (!$this->previous_value || $this->previous_value == 0) {
            return 0;
        }

        return (($this->current_value - $this->previous_value) / $this->previous_value) * 100;
    }

    public function getTargetVarianceAttribute(): float
    {
        if (!$this->target_value || $this->target_value == 0) {
            return 0;
        }

        return (($this->current_value - $this->target_value) / $this->target_value) * 100;
    }

    public function getPerformanceStatusAttribute(): string
    {
        $targetVariance = $this->target_variance;

        if ($targetVariance >= 0) {
            return 'on_target';
        } elseif ($targetVariance >= -10) {
            return 'below_target';
        } else {
            return 'critical';
        }
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    public function scopeByPeriod($query, string $periodType)
    {
        return $query->where('period_type', $periodType);
    }

    public function scopeCurrent($query)
    {
        return $query->where('period_end', '>=', now()->startOfDay());
    }
}
