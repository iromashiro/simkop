<?php
// app/Infrastructure/Tenancy/TenantScope.php
namespace App\Infrastructure\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * SECURITY HARDENED: Global scope for automatic tenant data isolation
 *
 * Ensures all queries are automatically filtered by cooperative_id
 * with proper SQL injection protection
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantManager::class)->getCurrentTenantId();

        if ($tenantId && $this->shouldApplyScope($model)) {
            // FIXED: Use qualifyColumn to prevent SQL injection
            $builder->where($model->qualifyColumn('cooperative_id'), $tenantId);
        }
    }

    /**
     * Extend the query builder with methods to bypass the scope
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenantScope', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('forTenant', function (Builder $builder, int $tenantId) {
            // SECURITY: Validate tenant ID is numeric and positive
            if (!is_int($tenantId) || $tenantId <= 0) {
                throw new \InvalidArgumentException('Invalid tenant ID provided');
            }

            return $builder->withoutGlobalScope($this)
                ->where($builder->getModel()->qualifyColumn('cooperative_id'), $tenantId);
        });

        $builder->macro('forAllTenants', function (Builder $builder) {
            // SECURITY: Log when tenant scope is bypassed
            if (app()->environment('production')) {
                \Log::warning('Tenant scope bypassed', [
                    'user_id' => auth()->id(),
                    'model' => get_class($builder->getModel()),
                    'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
                ]);
            }

            return $builder->withoutGlobalScope($this);
        });
    }

    /**
     * Determine if scope should be applied to model
     */
    private function shouldApplyScope(Model $model): bool
    {
        // SECURITY: More robust check for tenant-aware models
        return $model instanceof \App\Infrastructure\Tenancy\TenantModel ||
            in_array('cooperative_id', $model->getFillable()) ||
            array_key_exists('cooperative_id', $model->getAttributes()) ||
            $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'cooperative_id');
    }
}
