<?php
// app/Infrastructure/Monitoring/DatabaseMonitor.php
namespace App\Infrastructure\Monitoring;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DatabaseMonitor
{
    /**
     * Check overall database health
     */
    public function checkHealth(): array
    {
        try {
            return [
                'status' => 'healthy',
                'connection_count' => $this->getConnectionCount(),
                'slow_queries' => $this->getSlowQueries(),
                'index_usage' => $this->getIndexUsage(),
                'table_sizes' => $this->getTableSizes(),
                'cache_hit_ratio' => $this->getCacheHitRatio(),
                'disk_usage' => $this->getDiskUsage(),
                'last_checked' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::error('Database health check failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_checked' => now()->toISOString(),
            ];
        }
    }

    /**
     * Get active connection count
     */
    private function getConnectionCount(): int
    {
        try {
            $result = DB::select('SELECT count(*) as count FROM pg_stat_activity WHERE state = ?', ['active']);
            return $result[0]->count ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to get connection count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get slow queries
     */
    private function getSlowQueries(): array
    {
        try {
            $queries = DB::select("
                SELECT
                    query,
                    calls,
                    total_exec_time,
                    mean_exec_time,
                    rows
                FROM pg_stat_statements
                WHERE mean_exec_time > 1000
                ORDER BY mean_exec_time DESC
                LIMIT 10
            ");

            return array_map(function ($query) {
                return [
                    'query' => substr($query->query, 0, 100) . '...',
                    'calls' => $query->calls,
                    'avg_time' => round($query->mean_exec_time, 2) . 'ms',
                    'total_time' => round($query->total_exec_time, 2) . 'ms',
                ];
            }, $queries);
        } catch (\Exception $e) {
            Log::error('Failed to get slow queries', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get index usage statistics
     */
    private function getIndexUsage(): array
    {
        try {
            $indexes = DB::select("
                SELECT
                    schemaname,
                    tablename,
                    indexname,
                    idx_tup_read,
                    idx_tup_fetch
                FROM pg_stat_user_indexes
                WHERE idx_tup_read = 0
                ORDER BY tablename
                LIMIT 20
            ");

            return array_map(function ($index) {
                return [
                    'table' => $index->tablename,
                    'index' => $index->indexname,
                    'reads' => $index->idx_tup_read,
                    'fetches' => $index->idx_tup_fetch,
                    'status' => $index->idx_tup_read == 0 ? 'unused' : 'used',
                ];
            }, $indexes);
        } catch (\Exception $e) {
            Log::error('Failed to get index usage', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get table sizes
     */
    private function getTableSizes(): array
    {
        try {
            $tables = DB::select("
                SELECT
                    tablename,
                    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size,
                    pg_total_relation_size(schemaname||'.'||tablename) as size_bytes
                FROM pg_tables
                WHERE schemaname = 'public'
                ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
                LIMIT 10
            ");

            return array_map(function ($table) {
                return [
                    'table' => $table->tablename,
                    'size' => $table->size,
                    'size_bytes' => $table->size_bytes,
                ];
            }, $tables);
        } catch (\Exception $e) {
            Log::error('Failed to get table sizes', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get cache hit ratio
     */
    private function getCacheHitRatio(): float
    {
        try {
            $result = DB::select("
                SELECT
                    sum(heap_blks_hit) / (sum(heap_blks_hit) + sum(heap_blks_read)) * 100 as hit_ratio
                FROM pg_statio_user_tables
            ");

            return round($result[0]->hit_ratio ?? 0, 2);
        } catch (\Exception $e) {
            Log::error('Failed to get cache hit ratio', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage(): array
    {
        try {
            $result = DB::select("
                SELECT
                    pg_size_pretty(pg_database_size(current_database())) as database_size,
                    pg_database_size(current_database()) as database_size_bytes
            ");

            return [
                'database_size' => $result[0]->database_size ?? 'Unknown',
                'database_size_bytes' => $result[0]->database_size_bytes ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get disk usage', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get performance recommendations
     */
    public function getRecommendations(): array
    {
        $recommendations = [];

        try {
            // Check for unused indexes
            $unusedIndexes = $this->getUnusedIndexes();
            if (count($unusedIndexes) > 0) {
                $recommendations[] = [
                    'type' => 'performance',
                    'priority' => 'medium',
                    'message' => 'Found ' . count($unusedIndexes) . ' unused indexes that can be dropped',
                    'action' => 'review_unused_indexes',
                ];
            }

            // Check for missing indexes
            $missingIndexes = $this->getMissingIndexes();
            if (count($missingIndexes) > 0) {
                $recommendations[] = [
                    'type' => 'performance',
                    'priority' => 'high',
                    'message' => 'Found ' . count($missingIndexes) . ' tables that might benefit from additional indexes',
                    'action' => 'add_missing_indexes',
                ];
            }

            // Check cache hit ratio
            $hitRatio = $this->getCacheHitRatio();
            if ($hitRatio < 95) {
                $recommendations[] = [
                    'type' => 'performance',
                    'priority' => 'high',
                    'message' => "Cache hit ratio is {$hitRatio}%, consider increasing shared_buffers",
                    'action' => 'increase_cache',
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to generate recommendations', ['error' => $e->getMessage()]);
        }

        return $recommendations;
    }

    /**
     * Get unused indexes
     */
    private function getUnusedIndexes(): array
    {
        try {
            return DB::select("
                SELECT
                    schemaname,
                    tablename,
                    indexname
                FROM pg_stat_user_indexes
                WHERE idx_tup_read = 0
                AND idx_tup_fetch = 0
                AND indexname NOT LIKE '%_pkey'
            ");
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get tables that might need indexes
     */
    private function getMissingIndexes(): array
    {
        try {
            return DB::select("
                SELECT
                    tablename,
                    seq_scan,
                    seq_tup_read,
                    idx_scan,
                    idx_tup_fetch
                FROM pg_stat_user_tables
                WHERE seq_scan > idx_scan
                AND seq_tup_read > 10000
                ORDER BY seq_tup_read DESC
            ");
        } catch (\Exception $e) {
            return [];
        }
    }
}
