<?php
// app/Http/Middleware/ResolveTenantMiddleware.php
namespace App\Http\Middleware;

use App\Infrastructure\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * SECURITY HARDENED: Middleware for resolving tenant context with enhanced validation
 */
class ResolveTenantMiddleware
{
    public function __construct(
        private readonly TenantManager $tenantManager
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        // Skip tenant resolution for certain routes
        if ($this->shouldSkipTenantResolution($request)) {
            return $next($request);
        }

        // Resolve tenant from request
        $tenant = $this->tenantManager->resolve();

        if (!$tenant && $this->requiresTenant($request)) {
            $this->logSecurityEvent('tenant_not_found', $request);
            abort(403, 'Tenant context required but not found');
        }

        // SECURITY FIX: Enhanced tenant validation
        if ($tenant) {
            $this->validateTenantAccess($request, $tenant);
        }

        return $next($request);
    }

    /**
     * SECURITY FIX: Enhanced tenant access validation
     */
    private function validateTenantAccess(Request $request, $tenant): void
    {
        $user = Auth::user();

        if (!$user) {
            return; // Let auth middleware handle this
        }

        // Validate tenant is active
        if ($tenant->status !== 'active') {
            $this->logSecurityEvent('inactive_tenant_access', $request, [
                'tenant_id' => $tenant->id,
                'tenant_status' => $tenant->status,
            ]);
            abort(403, 'Cooperative is not active');
        }

        // Check if user belongs to tenant with caching
        $cacheKey = "user_tenant_access:{$user->id}:{$tenant->id}";
        $hasAccess = Cache::remember($cacheKey, 300, function () use ($user, $tenant) {
            return $this->tenantManager->validateTenantAccess($user->id, $tenant->id);
        });

        if (!$hasAccess) {
            $this->logSecurityEvent('unauthorized_tenant_access', $request, [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'user_cooperative_id' => $user->cooperative_id,
            ]);

            // Clear any cached access for this user
            Cache::forget($cacheKey);

            abort(403, 'Access denied to this cooperative');
        }

        // SECURITY: Additional validation for super admins
        if ($user->isSuperAdmin()) {
            $this->logSecurityEvent('super_admin_tenant_access', $request, [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
            ]);
        }

        // SECURITY: Rate limiting per tenant
        $this->enforceRateLimiting($request, $user, $tenant);
    }

    /**
     * SECURITY: Enforce rate limiting per tenant
     */
    private function enforceRateLimiting(Request $request, $user, $tenant): void
    {
        $key = "tenant_rate_limit:{$tenant->id}:{$user->id}:" . now()->format('Y-m-d-H-i');
        $attempts = Cache::increment($key);

        if ($attempts === 1) {
            Cache::put($key, 1, 60); // 1 minute window
        }

        $maxAttempts = config('tenancy.rate_limit_per_minute', 100);

        if ($attempts > $maxAttempts) {
            $this->logSecurityEvent('rate_limit_exceeded', $request, [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'attempts' => $attempts,
            ]);

            abort(429, 'Too many requests');
        }
    }

    /**
     * SECURITY: Enhanced security event logging
     */
    private function logSecurityEvent(string $event, Request $request, array $context = []): void
    {
        Log::warning("Security event: {$event}", array_merge([
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'timestamp' => now()->toISOString(),
        ], $context));
    }

    /**
     * Check if tenant resolution should be skipped
     */
    private function shouldSkipTenantResolution(Request $request): bool
    {
        $skipRoutes = [
            'login',
            'register',
            'password.*',
            'health-check',
            'api/auth/*',
            'telescope.*', // Laravel Telescope
            'horizon.*',   // Laravel Horizon
        ];

        foreach ($skipRoutes as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if route requires tenant context
     */
    private function requiresTenant(Request $request): bool
    {
        $tenantRequiredRoutes = [
            'dashboard',
            'financial.*',
            'members.*',
            'reports.*',
            'shu.*',
            'budget.*',
            'cooperative.show',
            'cooperative.edit',
        ];

        foreach ($tenantRequiredRoutes as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }
}
