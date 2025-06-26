<?php
// app/Infrastructure/Performance/CacheManager.php
namespace App\Infrastructure\Performance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheManager
{
    /**
     * Cache keys for different data types
     */
    private const CACHE_KEYS = [
        'cooperative_stats' => 'coop:{id}:stats',
        'member_stats' => 'member:{id}:stats',
        'financial_summary' => 'coop:{id}:financial:summary',
        'dashboard_widgets' => 'user:{id}:dashboard:widgets',
        'reports' => 'coop:{id}:report:{type}:{hash}',
    ];

    /**
     * Cache TTL in seconds
     */
    private const CACHE_TTL = [
        'cooperative_stats' => 3600, // 1 hour
        'member_stats' => 1800, // 30 minutes
        'financial_summary' => 900, // 15 minutes
        'dashboard_widgets' => 600, // 10 minutes
        'reports' => 7200, // 2 hours
    ];

    /**
     * Get cache key for data type
     */
    public static function getKey(string $type, array $params = []): string
    {
        $template = self::CACHE_KEYS[$type] ?? $type;

        foreach ($params as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }

    /**
     * Cache data with automatic TTL
     */
    public static function put(string $type, array $params, mixed $data): bool
    {
        $key = self::getKey($type, $params);
        $ttl = self::CACHE_TTL[$type] ?? 3600;

        return Cache::put($key, $data, $ttl);
    }

    /**
     * Get cached data
     */
    public static function get(string $type, array $params): mixed
    {
        $key = self::getKey($type, $params);
        return Cache::get($key);
    }

    /**
     * Remember data in cache
     */
    public static function remember(string $type, array $params, callable $callback): mixed
    {
        $key = self::getKey($type, $params);
        $ttl = self::CACHE_TTL[$type] ?? 3600;

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Invalidate cache by pattern
     */
    public static function invalidatePattern(string $pattern): int
    {
        $keys = Redis::keys($pattern);

        if (empty($keys)) {
            return 0;
        }

        return Redis::del($keys);
    }

    /**
     * Invalidate cooperative cache
     */
    public static function invalidateCooperative(int $cooperativeId): void
    {
        self::invalidatePattern("coop:{$cooperativeId}:*");
    }

    /**
     * Invalidate member cache
     */
    public static function invalidateMember(int $memberId): void
    {
        self::invalidatePattern("member:{$memberId}:*");
    }

    /**
     * Invalidate user cache
     */
    public static function invalidateUser(int $userId): void
    {
        self::invalidatePattern("user:{$userId}:*");
    }

    /**
     * Get cache statistics
     */
    public static function getStatistics(): array
    {
        $info = Redis::info('memory');

        return [
            'used_memory' => $info['used_memory_human'] ?? 'N/A',
            'used_memory_peak' => $info['used_memory_peak_human'] ?? 'N/A',
            'total_keys' => Redis::dbsize(),
            'hit_rate' => self::calculateHitRate(),
        ];
    }

    /**
     * Calculate cache hit rate
     */
    private static function calculateHitRate(): float
    {
        $info = Redis::info('stats');
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }
}
