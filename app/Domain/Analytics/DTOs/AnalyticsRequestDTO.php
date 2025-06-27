<?php
// app/Domain/Analytics/DTOs/AnalyticsRequestDTO.php
namespace App\Domain\Analytics\DTOs;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Analytics Request Data Transfer Object - Enhanced Version
 * SRS Reference: Section 3.6.1 - Analytics Request Structure
 *
 * @author Mateen (Senior Software Engineer)
 * @version 2.0 - Enhanced based on Mikail's review
 */
class AnalyticsRequestDTO
{
    public function __construct(
        public int $cooperativeId,
        public string $period = 'monthly',
        public array $widgets = [],
        public array $filters = [],
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public array $metrics = [],
        public bool $includeComparisons = false,
        public bool $includeTrends = true,
        public string $granularity = 'daily',
        public array $kpis = [],
        public ?int $userId = null
    ) {
        $this->validatePeriod();
        $this->validateDateRange();
        $this->validateCooperativeId();
        $this->setDefaultWidgets();
        $this->sanitizeInputs();
        $this->userId = $this->userId ?? auth()->id();
    }

    /**
     * Create from array data with enhanced validation
     */
    public static function fromArray(array $data): self
    {
        // Sanitize input data
        $sanitizedData = self::sanitizeArrayData($data);

        return new self(
            cooperativeId: $sanitizedData['cooperative_id'],
            period: $sanitizedData['period'] ?? 'monthly',
            widgets: $sanitizedData['widgets'] ?? [],
            filters: $sanitizedData['filters'] ?? [],
            dateFrom: $sanitizedData['date_from'] ?? null,
            dateTo: $sanitizedData['date_to'] ?? null,
            metrics: $sanitizedData['metrics'] ?? [],
            includeComparisons: $sanitizedData['include_comparisons'] ?? false,
            includeTrends: $sanitizedData['include_trends'] ?? true,
            granularity: $sanitizedData['granularity'] ?? 'daily',
            kpis: $sanitizedData['kpis'] ?? [],
            userId: $sanitizedData['user_id'] ?? auth()->id()
        );
    }

    /**
     * Create from HTTP request with validation
     */
    public static function fromRequest(Request $request): self
    {
        // Use validated data instead of all() - Mikail's suggestion
        $validatedData = $request->validate([
            'cooperative_id' => 'required|integer|min:1',
            'period' => 'string|in:daily,weekly,monthly,quarterly,yearly',
            'widgets' => 'array',
            'widgets.*' => 'string',
            'filters' => 'array',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'metrics' => 'array',
            'include_comparisons' => 'boolean',
            'include_trends' => 'boolean',
            'granularity' => 'string|in:hourly,daily,weekly,monthly',
            'kpis' => 'array',
            'user_id' => 'nullable|integer|min:1'
        ]);

        return self::fromArray($validatedData);
    }

    /**
     * Get date range based on period with configuration support
     */
    public function getDateRange(): array
    {
        if ($this->dateFrom && $this->dateTo) {
            return [
                'from' => Carbon::parse($this->dateFrom),
                'to' => Carbon::parse($this->dateTo)
            ];
        }

        $now = Carbon::now();

        return match ($this->period) {
            'daily' => [
                'from' => $now->copy()->subDays(config('analytics.default_periods.daily', 30)),
                'to' => $now
            ],
            'weekly' => [
                'from' => $now->copy()->subWeeks(config('analytics.default_periods.weekly', 12)),
                'to' => $now
            ],
            'monthly' => [
                'from' => $now->copy()->subMonths(config('analytics.default_periods.monthly', 12)),
                'to' => $now
            ],
            'quarterly' => [
                'from' => $now->copy()->subQuarters(config('analytics.default_periods.quarterly', 4)),
                'to' => $now
            ],
            'yearly' => [
                'from' => $now->copy()->subYears(config('analytics.default_periods.yearly', 3)),
                'to' => $now
            ],
            default => [
                'from' => $now->copy()->subMonths(12),
                'to' => $now
            ]
        };
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'cooperative_id' => $this->cooperativeId,
            'period' => $this->period,
            'widgets' => $this->widgets,
            'filters' => $this->filters,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'metrics' => $this->metrics,
            'include_comparisons' => $this->includeComparisons,
            'include_trends' => $this->includeTrends,
            'granularity' => $this->granularity,
            'kpis' => $this->kpis,
            'user_id' => $this->userId,
        ];
    }

    /**
     * Get cache key for this request
     */
    public function getCacheKey(): string
    {
        return "analytics_request:" . md5(serialize($this->toArray()));
    }

    /**
     * Validate period with configuration support
     */
    private function validatePeriod(): void
    {
        $validPeriods = config('analytics.valid_periods', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']);

        if (!in_array($this->period, $validPeriods)) {
            throw new \InvalidArgumentException(
                "Invalid period: {$this->period}. Must be one of: " . implode(', ', $validPeriods)
            );
        }
    }

    /**
     * Validate date range with configurable limits - Enhanced based on Mikail's feedback
     */
    private function validateDateRange(): void
    {
        if ($this->dateFrom && $this->dateTo) {
            $from = Carbon::parse($this->dateFrom);
            $to = Carbon::parse($this->dateTo);

            if ($from->gt($to)) {
                throw new \InvalidArgumentException('Date from cannot be greater than date to');
            }

            $maxDays = config('analytics.max_date_range_days', 365);
            if ($from->diffInDays($to) > $maxDays) {
                throw new \InvalidArgumentException(
                    "Date range cannot exceed {$maxDays} days. Current range: " . $from->diffInDays($to) . " days"
                );
            }

            // Validate date is not in future
            if ($to->gt(Carbon::now())) {
                throw new \InvalidArgumentException('End date cannot be in the future');
            }

            // Validate minimum date range
            $minDays = config('analytics.min_date_range_days', 1);
            if ($from->diffInDays($to) < $minDays) {
                throw new \InvalidArgumentException(
                    "Date range must be at least {$minDays} day(s)"
                );
            }
        }
    }

    /**
     * Validate cooperative ID
     */
    private function validateCooperativeId(): void
    {
        if ($this->cooperativeId <= 0) {
            throw new \InvalidArgumentException('Cooperative ID must be a positive integer');
        }

        // Check if cooperative exists (optional, can be disabled for performance)
        if (config('analytics.validate_cooperative_exists', true)) {
            $cooperativeExists = \App\Domain\Cooperative\Models\Cooperative::where('id', $this->cooperativeId)->exists();
            if (!$cooperativeExists) {
                throw new \InvalidArgumentException("Cooperative with ID {$this->cooperativeId} does not exist");
            }
        }
    }

    /**
     * Set default widgets if none provided
     */
    private function setDefaultWidgets(): void
    {
        if (empty($this->widgets)) {
            $this->widgets = config('analytics.default_widgets', [
                'financial_overview',
                'member_growth',
                'savings_trends',
                'loan_portfolio'
            ]);
        }

        // Validate widget types
        $validWidgets = config('analytics.valid_widgets', [
            'financial_overview',
            'member_growth',
            'savings_trends',
            'loan_portfolio',
            'profitability',
            'risk_metrics'
        ]);

        foreach ($this->widgets as $widget) {
            if (!in_array($widget, $validWidgets)) {
                throw new \InvalidArgumentException(
                    "Invalid widget: {$widget}. Valid widgets: " . implode(', ', $validWidgets)
                );
            }
        }
    }

    /**
     * Sanitize inputs to prevent XSS and injection attacks
     */
    private function sanitizeInputs(): void
    {
        // Sanitize string inputs
        if ($this->dateFrom) {
            $this->dateFrom = htmlspecialchars(strip_tags($this->dateFrom), ENT_QUOTES, 'UTF-8');
        }

        if ($this->dateTo) {
            $this->dateTo = htmlspecialchars(strip_tags($this->dateTo), ENT_QUOTES, 'UTF-8');
        }

        $this->period = htmlspecialchars(strip_tags($this->period), ENT_QUOTES, 'UTF-8');
        $this->granularity = htmlspecialchars(strip_tags($this->granularity), ENT_QUOTES, 'UTF-8');

        // Sanitize arrays
        $this->widgets = array_map(function ($widget) {
            return htmlspecialchars(strip_tags($widget), ENT_QUOTES, 'UTF-8');
        }, $this->widgets);

        $this->metrics = array_map(function ($metric) {
            return htmlspecialchars(strip_tags($metric), ENT_QUOTES, 'UTF-8');
        }, $this->metrics);

        $this->kpis = array_map(function ($kpi) {
            return htmlspecialchars(strip_tags($kpi), ENT_QUOTES, 'UTF-8');
        }, $this->kpis);
    }

    /**
     * Sanitize array data
     */
    private static function sanitizeArrayData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
            } elseif (is_array($value)) {
                $sanitized[$key] = array_map(function ($item) {
                    return is_string($item) ? htmlspecialchars(strip_tags($item), ENT_QUOTES, 'UTF-8') : $item;
                }, $value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if request is valid for caching
     */
    public function isCacheable(): bool
    {
        // Don't cache real-time requests
        if (in_array('real_time', $this->filters)) {
            return false;
        }

        // Don't cache requests with very short date ranges
        $dateRange = $this->getDateRange();
        if ($dateRange['from']->diffInHours($dateRange['to']) < 24) {
            return false;
        }

        return true;
    }

    /**
     * Get estimated processing time in seconds
     */
    public function getEstimatedProcessingTime(): int
    {
        $baseTime = 5; // Base 5 seconds

        // Add time based on widgets count
        $baseTime += count($this->widgets) * 2;

        // Add time for trends and comparisons
        if ($this->includeTrends) $baseTime += 3;
        if ($this->includeComparisons) $baseTime += 5;

        // Add time based on date range
        $dateRange = $this->getDateRange();
        $days = $dateRange['from']->diffInDays($dateRange['to']);
        if ($days > 90) $baseTime += 10;
        elseif ($days > 30) $baseTime += 5;

        return min($baseTime, config('analytics.max_processing_time', 60));
    }
}
