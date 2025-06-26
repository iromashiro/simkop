<?php
// app/Infrastructure/Tenancy/TenantManager.php
namespace App\Infrastructure\Tenancy;

use App\Domain\Cooperative\Models\Cooperative;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Central tenant management system for HERMES multi-tenant architecture
 *
 * Handles tenant resolution, context switching, and data isolation
 * for 300+ cooperatives in Muara Enim Regency
 */
class TenantManager
{
    private static ?Cooperative $currentTenant = null;
    private static array $resolvers = [];

    public function __construct()
    {
        $this->registerResolvers();
    }

    /**
     * Register tenant resolution strategies
     */
    private function registerResolvers(): void
    {
        $strategies = config('tenancy.resolution_strategies', []);

        foreach ($strategies as $name => $class) {
            self::$resolvers[$name] = app($class);
        }
    }

    /**
     * Resolve tenant from current request context
     */
    public function resolve(): ?Cooperative
    {
        if (self::$currentTenant) {
            return self::$currentTenant;
        }

        foreach (self::$resolvers as $resolver) {
            if ($tenant = $resolver->resolve()) {
                return $this->setTenant($tenant);
            }
        }

        return null;
    }

    /**
     * Set current tenant and apply global scopes
     */
    public function setTenant(Cooperative $tenant): Cooperative
    {
        self::$currentTenant = $tenant;

        // Apply global scopes to all tenant models
        $this->applyGlobalScopes();

        // Set cache context
        $this->setCacheContext($tenant->id);

        return $tenant;
    }

    /**
     * Get current tenant
     */
    public function getCurrentTenant(): ?Cooperative
    {
        return self::$currentTenant;
    }

    /**
     * Get current tenant ID
     */
    public function getCurrentTenantId(): ?int
    {
        return self::$currentTenant?->id;
    }

    /**
     * Clear tenant context
     */
    public function clearTenant(): void
    {
        self::$currentTenant = null;
        $this->removeGlobalScopes();
    }

    /**
     * Apply global tenant scopes to all models
     */
    private function applyGlobalScopes(): void
    {
        $models = config('tenancy.models', []);

        foreach ($models as $model) {
            if (!$model::hasGlobalScope('tenant')) {
                $model::addGlobalScope('tenant', new TenantScope());
            }
        }
    }

    /**
     * Remove global tenant scopes
     */
    private function removeGlobalScopes(): void
    {
        $models = config('tenancy.models', []);

        foreach ($models as $model) {
            $model::withoutGlobalScope('tenant');
        }
    }

    /**
     * Set cache context for tenant isolation
     */
    private function setCacheContext(int $tenantId): void
    {
        Cache::setDefaultKey("tenant:{$tenantId}");
    }

    /**
     * Execute callback without tenant scope
     */
    public function withoutTenantScope(callable $callback): mixed
    {
        $currentTenant = self::$currentTenant;
        $this->clearTenant();

        try {
            return $callback();
        } finally {
            if ($currentTenant) {
                $this->setTenant($currentTenant);
            }
        }
    }

    /**
     * Validate tenant access for user
     */
    public function validateTenantAccess(int $userId, int $tenantId): bool
    {
        return Cache::remember(
            "user:{$userId}:tenant:{$tenantId}:access",
            3600,
            function () use ($userId, $tenantId) {
                return DB::table('users')
                    ->where('id', $userId)
                    ->where(function ($query) use ($tenantId) {
                        $query->where('cooperative_id', $tenantId)
                            ->orWhereNull('cooperative_id'); // Super admin
                    })
                    ->exists();
            }
        );
    }
}
