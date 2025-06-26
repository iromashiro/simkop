<?php
// app/Infrastructure/Tenancy/TenantModel.php
namespace App\Infrastructure\Tenancy;

use Illuminate\Database\Eloquent\Model;
use App\Domain\Cooperative\Models\Cooperative;

/**
 * Base model for all tenant-aware entities
 *
 * Automatically handles tenant assignment and validation
 */
abstract class TenantModel extends Model
{
    /**
     * Boot the tenant model
     */
    protected static function booted(): void
    {
        // Automatically set tenant on creation
        static::creating(function ($model) {
            if (!$model->cooperative_id) {
                $tenantId = app(TenantManager::class)->getCurrentTenantId();
                if ($tenantId) {
                    $model->cooperative_id = $tenantId;
                }
            }
        });

        // Validate tenant on update
        static::updating(function ($model) {
            $currentTenantId = app(TenantManager::class)->getCurrentTenantId();

            if ($currentTenantId && $model->cooperative_id !== $currentTenantId) {
                throw new \Exception(
                    "Cannot update model belonging to different tenant. " .
                        "Model tenant: {$model->cooperative_id}, Current tenant: {$currentTenantId}"
                );
            }
        });

        // Apply global tenant scope
        static::addGlobalScope(new TenantScope());
    }

    /**
     * Get the cooperative that owns this model
     */
    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    /**
     * Scope query to specific tenant
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('cooperative_id', $tenantId);
    }

    /**
     * Check if model belongs to current tenant
     */
    public function belongsToCurrentTenant(): bool
    {
        $currentTenantId = app(TenantManager::class)->getCurrentTenantId();
        return $this->cooperative_id === $currentTenantId;
    }
}
