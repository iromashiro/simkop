<?php
// app/Domain/Analytics/Models/AnalyticsData.php
namespace App\Domain\Analytics\Models;

use App\Infrastructure\Tenancy\TenantModel;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsData extends TenantModel
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'cooperative_id',
        'data_type',
        'category',
        'label',
        'value',
        'additional_data',
        'recorded_at',
        'period_type',
        'period_identifier',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'additional_data' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Cooperative\Models\Cooperative::class);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('data_type', $type);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByPeriod($query, string $periodType, string $periodIdentifier)
    {
        return $query->where('period_type', $periodType)
            ->where('period_identifier', $periodIdentifier);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }
}
