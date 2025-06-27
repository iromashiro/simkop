<?php
// app/Domain/Analytics/DTOs/AnalyticsResultDTO.php
namespace App\Domain\Analytics\DTOs;

use Carbon\Carbon;

/**
 * Analytics Result Data Transfer Object - Enhanced Version
 * SRS Reference: Section 3.6.2 - Analytics Response Structure
 *
 * @author Mateen (Senior Software Engineer)
 * @version 2.0 - Enhanced based on Mikail's review
 */
class AnalyticsResultDTO
{
    public function __construct(
        public array $widgets,
        public array $metadata,
        public int $cooperativeId,
        public array $kpis = [],
        public array $trends = [],
        public array $comparisons = [],
        public array $alerts = [],
        public array $summary = [],
        public ?string $generatedAt = null,
        public array $performance = []
    ) {
        $this->generatedAt = $this->generatedAt ?? Carbon::now()->toISOString();
        $this->validateData();
        $this->addDefaultMetadata();
    }

    /**
     * Create success result with enhanced metadata
     */
    public static function success(
        array $widgets,
        int $cooperativeId,
        array $metadata = [],
        array $kpis = [],
        array $trends = [],
        array $comparisons = [],
        array $alerts = [],
        array $performance = []
    ): self {
        return new self(
            widgets: $widgets,
            metadata: array_merge([
                'status' => 'success',
                'generated_at' => Carbon::now()->toISOString(),
                'version' => '2.0',
                'api_version' => config('app.api_version', '1.0'),
            ], $metadata),
            cooperativeId: $cooperativeId,
            kpis: $kpis,
            trends: $trends,
            comparisons: $comparisons,
            alerts: $alerts,
            performance: $performance
        );
    }

    /**
     * Create error result with enhanced context - Mikail's suggestion
     */
    public static function error(
        string $message,
        int $cooperativeId,
        array $metadata = [],
        ?\Throwable $exception = null
    ): self {
        $errorMetadata = [
            'status' => 'error',
            'error_message' => $message,
            'generated_at' => Carbon::now()->toISOString(),
            'error_code' => $exception?->getCode() ?? 500,
            'error_type' => $exception ? get_class($exception) : 'UnknownError',
        ];

        // Add debug information in development
        if (config('app.debug') && $exception) {
            $errorMetadata['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return new self(
            widgets: [],
            metadata: array_merge($errorMetadata, $metadata),
            cooperativeId: $cooperativeId
        );
    }

    /**
     * Convert to array with enhanced structure
     */
    public function toArray(): array
    {
        return [
            'data' => [
                'widgets' => $this->widgets,
                'kpis' => $this->kpis,
                'trends' => $this->trends,
                'comparisons' => $this->comparisons,
                'alerts' => $this->alerts,
                'summary' => $this->summary,
            ],
            'metadata' => $this->metadata,
            'cooperative_id' => $this->cooperativeId,
            'generated_at' => $this->generatedAt,
            'performance' => $this->performance,
        ];
    }

    /**
     * Convert to JSON with pretty formatting
     */
    public function toJson(bool $prettyPrint = false): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($this->toArray(), $flags);
    }

    /**
     * Get widget by type with error handling
     */
    public function getWidget(string $type): ?array
    {
        if (!isset($this->widgets[$type])) {
            \Log::warning("Widget type '{$type}' not found in analytics result", [
                'available_widgets' => array_keys($this->widgets),
                'cooperative_id' => $this->cooperativeId
            ]);
            return null;
        }

        return $this->widgets[$type];
    }

    /**
     * Add widget with validation
     */
    public function addWidget(string $type, array $data): self
    {
        if (empty($type)) {
            throw new \InvalidArgumentException('Widget type cannot be empty');
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Widget data must be an array');
        }

        $this->widgets[$type] = $data;
        $this->updateMetadata('widgets_count', count($this->widgets));

        return $this;
    }

    /**
     * Add KPI with enhanced metadata
     */
    public function addKPI(string $name, mixed $value, ?string $trend = null, array $metadata = []): self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('KPI name cannot be empty');
        }

        $this->kpis[$name] = [
            'value' => $value,
            'trend' => $trend,
            'metadata' => $metadata,
            'updated_at' => Carbon::now()->toISOString()
        ];

        return $this;
    }

    /**
     * Add alert with severity validation
     */
    public function addAlert(string $type, string $message, string $severity = 'info', array $metadata = []): self
    {
        $validSeverities = ['info', 'warning', 'error', 'critical'];

        if (!in_array($severity, $validSeverities)) {
            throw new \InvalidArgumentException(
                "Invalid alert severity: {$severity}. Valid severities: " . implode(', ', $validSeverities)
            );
        }

        $this->alerts[] = [
            'id' => uniqid('alert_'),
            'type' => $type,
            'message' => $message,
            'severity' => $severity,
            'metadata' => $metadata,
            'created_at' => Carbon::now()->toISOString()
        ];

        return $this;
    }

    /**
     * Add cache metadata - Mikail's suggestion
     */
    public function addCacheMetadata(string $cacheKey, int $ttl, bool $fromCache = false): self
    {
        $this->metadata['cache'] = [
            'key' => $cacheKey,
            'ttl' => $ttl,
            'from_cache' => $fromCache,
            'cached_at' => Carbon::now()->toISOString(),
            'expires_at' => Carbon::now()->addSeconds($ttl)->toISOString()
        ];

        return $this;
    }

    /**
     * Add performance metrics
     */
    public function addPerformanceMetrics(array $metrics): self
    {
        $this->performance = array_merge($this->performance, [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'execution_time' => $metrics['execution_time'] ?? 0,
            'query_count' => $metrics['query_count'] ?? 0,
            'cache_hits' => $metrics['cache_hits'] ?? 0,
            'cache_misses' => $metrics['cache_misses'] ?? 0,
        ], $metrics);

        return $this;
    }

    /**
     * Check if result has errors
     */
    public function hasErrors(): bool
    {
        return ($this->metadata['status'] ?? 'success') === 'error';
    }

    /**
     * Check if result has warnings
     */
    public function hasWarnings(): bool
    {
        return !empty(array_filter($this->alerts, fn($alert) => $alert['severity'] === 'warning'));
    }

    /**
     * Check if result has critical alerts
     */
    public function hasCriticalAlerts(): bool
    {
        return !empty(array_filter($this->alerts, fn($alert) => $alert['severity'] === 'critical'));
    }

    /**
     * Get execution time
     */
    public function getExecutionTime(): ?float
    {
        return $this->metadata['execution_time'] ?? $this->performance['execution_time'] ?? null;
    }

    /**
     * Get memory usage
     */
    public function getMemoryUsage(): array
    {
        return [
            'current' => $this->performance['memory_usage'] ?? memory_get_usage(true),
            'peak' => $this->performance['peak_memory'] ?? memory_get_peak_usage(true),
            'formatted' => [
                'current' => $this->formatBytes($this->performance['memory_usage'] ?? memory_get_usage(true)),
                'peak' => $this->formatBytes($this->performance['peak_memory'] ?? memory_get_peak_usage(true))
            ]
        ];
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'from_cache' => $this->metadata['cache']['from_cache'] ?? false,
            'cache_key' => $this->metadata['cache']['key'] ?? null,
            'ttl' => $this->metadata['cache']['ttl'] ?? null,
            'hits' => $this->performance['cache_hits'] ?? 0,
            'misses' => $this->performance['cache_misses'] ?? 0,
            'hit_ratio' => $this->calculateCacheHitRatio()
        ];
    }

    /**
     * Update metadata
     */
    public function updateMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        $this->metadata['last_updated'] = Carbon::now()->toISOString();

        return $this;
    }

    /**
     * Validate data structure
     */
    private function validateData(): void
    {
        if (!is_array($this->widgets)) {
            throw new \InvalidArgumentException('Widgets must be an array');
        }

        if (!is_array($this->metadata)) {
            throw new \InvalidArgumentException('Metadata must be an array');
        }

        if ($this->cooperativeId <= 0) {
            throw new \InvalidArgumentException('Cooperative ID must be a positive integer');
        }

        // Validate alerts structure
        foreach ($this->alerts as $alert) {
            if (!isset($alert['type']) || !isset($alert['message']) || !isset($alert['severity'])) {
                throw new \InvalidArgumentException('Invalid alert structure');
            }
        }
    }

    /**
     * Add default metadata
     */
    private function addDefaultMetadata(): void
    {
        $this->metadata = array_merge([
            'widgets_count' => count($this->widgets),
            'kpis_count' => count($this->kpis),
            'alerts_count' => count($this->alerts),
            'has_trends' => !empty($this->trends),
            'has_comparisons' => !empty($this->comparisons),
            'server_time' => Carbon::now()->toISOString(),
            'timezone' => config('app.timezone', 'UTC'),
        ], $this->metadata);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Calculate cache hit ratio
     */
    private function calculateCacheHitRatio(): float
    {
        $hits = $this->performance['cache_hits'] ?? 0;
        $misses = $this->performance['cache_misses'] ?? 0;
        $total = $hits + $misses;

        return $total > 0 ? ($hits / $total) * 100 : 0;
    }

    /**
     * Export to different formats
     */
    public function export(string $format = 'json'): string
    {
        return match ($format) {
            'json' => $this->toJson(true),
            'csv' => $this->toCsv(),
            'xml' => $this->toXml(),
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}")
        };
    }

    /**
     * Convert to CSV format
     */
    private function toCsv(): string
    {
        // Implementation for CSV export
        $csv = "Type,Name,Value,Trend\n";

        foreach ($this->kpis as $name => $kpi) {
            $csv .= "KPI,{$name},{$kpi['value']},{$kpi['trend']}\n";
        }

        return $csv;
    }

    /**
     * Convert to XML format
     */
    private function toXml(): string
    {
        // Implementation for XML export
        $xml = new \SimpleXMLElement('<analytics/>');
        $xml->addChild('cooperative_id', $this->cooperativeId);
        $xml->addChild('generated_at', $this->generatedAt);

        // Add widgets
        $widgetsNode = $xml->addChild('widgets');
        foreach ($this->widgets as $type => $data) {
            $widgetNode = $widgetsNode->addChild('widget');
            $widgetNode->addAttribute('type', $type);
            $widgetNode->addChild('data', json_encode($data));
        }

        return $xml->asXML();
    }
}
