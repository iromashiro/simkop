<?php
// app/Infrastructure/Performance/QueryOptimizer.php
namespace App\Infrastructure\Performance;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QueryOptimizer
{
    /**
     * Optimize query with eager loading and monitoring
     */
    public static function optimizeQuery(Builder $query, array $relations = []): Builder
    {
        $startTime = microtime(true);

        if (!empty($relations)) {
            $query->with($relations);
        }

        // Add query monitoring
        $query->getConnection()->listen(function ($queryExecuted) use ($startTime) {
            $executionTime = microtime(true) - $startTime;

            if ($executionTime > 1.0) { // Log slow queries
                Log::warning('Slow query detected', [
                    'execution_time' => round($executionTime * 1000, 2) . 'ms',
                    'sql' => $queryExecuted->sql,
                    'bindings' => $queryExecuted->bindings,
                    'connection' => $queryExecuted->connectionName,
                ]);

                // Store slow query for analysis
                self::storeSlowQuery($queryExecuted, $executionTime);
            }
        });

        return $query;
    }

    /**
     * Add query caching with tags
     */
    public static function cacheQuery(Builder $query, string $key, int $ttl = 3600, array $tags = []): mixed
    {
        $cacheKey = self::generateCacheKey($key, $query);

        return Cache::tags($tags)->remember($cacheKey, $ttl, function () use ($query) {
            return $query->get();
        });
    }

    /**
     * Optimize pagination queries with cursor pagination for large datasets
     */
    public static function optimizePagination(Builder $query, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $table = $query->getModel()->getTable();
        $recordCount = self::getTableRecordCount($table);

        // Use cursor pagination for large datasets
        if ($recordCount > 100000 || in_array($table, ['journal_entries', 'savings_transactions', 'activity_logs'])) {
            return $query->simplePaginate($perPage);
        }

        // Use regular pagination for smaller datasets
        return $query->paginate($perPage);
    }

    /**
     * Add database indexes suggestions based on query analysis
     */
    public static function suggestIndexes(string $table): array
    {
        $suggestions = [];

        try {
            // Analyze slow queries for this table
            $slowQueries = DB::select("
                SELECT query_text, calls, mean_exec_time, rows
                FROM pg_stat_statements
                WHERE query_text LIKE ?
                ORDER BY mean_exec_time DESC
                LIMIT 10
            ", ["%{$table}%"]);

            foreach ($slowQueries as $query) {
                if ($query->mean_exec_time > 1000) { // More than 1 second
                    $suggestions[] = [
                        'table' => $table,
                        'query' => $query->query_text,
                        'avg_time' => round($query->mean_exec_time, 2) . 'ms',
                        'calls' => $query->calls,
                        'suggestion' => self::analyzeQueryForIndexSuggestion($query->query_text),
                        'priority' => self::calculateIndexPriority($query),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to analyze queries for index suggestions', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }

        return $suggestions;
    }

    /**
     * Get table record count with caching
     */
    private static function getTableRecordCount(string $table): int
    {
        return Cache::remember("table_count:{$table}", 3600, function () use ($table) {
            try {
                return DB::table($table)->count();
            } catch (\Exception $e) {
                Log::error("Failed to get record count for table: {$table}", [
                    'error' => $e->getMessage(),
                ]);
                return 0;
            }
        });
    }

    /**
     * Generate cache key for query
     */
    private static function generateCacheKey(string $key, Builder $query): string
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        $hash = md5($sql . serialize($bindings));

        return "query:{$key}:{$hash}";
    }

    /**
     * Store slow query for analysis
     */
    private static function storeSlowQuery($queryExecuted, float $executionTime): void
    {
        try {
            Cache::put(
                'slow_queries:' . date('Y-m-d-H'),
                [
                    'sql' => $queryExecuted->sql,
                    'bindings' => $queryExecuted->bindings,
                    'execution_time' => $executionTime,
                    'timestamp' => now(),
                ],
                3600 // 1 hour
            );
        } catch (\Exception $e) {
            // Fail silently to avoid breaking the application
            Log::error('Failed to store slow query', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Analyze query for index suggestions
     */
    private static function analyzeQueryForIndexSuggestion(string $query): string
    {
        // Simple analysis - in production, use more sophisticated query parsing
        if (preg_match('/WHERE\s+(\w+)\s*=/', $query, $matches)) {
            return "Consider adding index on column: {$matches[1]}";
        }

        if (preg_match('/ORDER BY\s+(\w+)/', $query, $matches)) {
            return "Consider adding index on column: {$matches[1]} for sorting";
        }

        if (preg_match('/JOIN\s+\w+\s+ON\s+\w+\.(\w+)\s*=\s*\w+\.(\w+)/', $query, $matches)) {
            return "Consider adding indexes on join columns: {$matches[1]}, {$matches[2]}";
        }

        return "Manual query analysis recommended";
    }

    /**
     * Calculate index priority based on query performance
     */
    private static function calculateIndexPriority($query): string
    {
        $avgTime = $query->mean_exec_time;
        $calls = $query->calls;

        $impact = $avgTime * $calls;

        if ($impact > 10000) {
            return 'high';
        } elseif ($impact > 5000) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get query performance statistics
     */
    public static function getQueryStats(): array
    {
        try {
            $stats = DB::select("
                SELECT
                    COUNT(*) as total_queries,
                    AVG(mean_exec_time) as avg_execution_time,
                    MAX(mean_exec_time) as max_execution_time,
                    SUM(calls) as total_calls
                FROM pg_stat_statements
            ");

            return [
                'total_queries' => $stats[0]->total_queries ?? 0,
                'avg_execution_time' => round($stats[0]->avg_execution_time ?? 0, 2),
                'max_execution_time' => round($stats[0]->max_execution_time ?? 0, 2),
                'total_calls' => $stats[0]->total_calls ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get query statistics', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
