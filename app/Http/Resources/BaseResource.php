<?php
// app/Http/Resources/BaseResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

abstract class BaseResource extends JsonResource
{
    /**
     * Sanitize string input
     */
    protected function sanitizeString(?string $value): ?string
    {
        return $value ? htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8') : null;
    }

    /**
     * Sanitize email input
     */
    protected function sanitizeEmail(?string $email): ?string
    {
        return $email ? filter_var($email, FILTER_SANITIZE_EMAIL) : null;
    }

    /**
     * Sanitize phone number
     */
    protected function sanitizePhone(?string $phone): ?string
    {
        return $phone ? preg_replace('/[^0-9+\-\s]/', '', $phone) : null;
    }

    /**
     * Format currency
     */
    protected function formatCurrency($amount): string
    {
        return number_format($amount, 2, '.', ',');
    }

    /**
     * Check if user can view sensitive data
     */
    protected function canViewSensitiveData(Request $request): bool
    {
        return $request->user()?->can('view_sensitive_data') ?? false;
    }

    /**
     * Cache resource data
     */
    protected function cacheResource(Request $request, callable $callback, int $ttl = 300): array
    {
        $cacheKey = $this->getCacheKey($request);

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Generate cache key for resource
     */
    protected function getCacheKey(Request $request): string
    {
        $table = $this->getTable() ?? 'resource';
        $userId = $request->user()?->id ?? 'guest';

        return "resource:{$table}:{$this->id}:{$userId}";
    }

    /**
     * Get table name from model
     */
    protected function getTable(): ?string
    {
        return $this->resource?->getTable();
    }
}
