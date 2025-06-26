<?php
// app/Domain/Analytics/DTOs/AnalyticsRequestDTO.php
namespace App\Domain\Analytics\DTOs;

use Carbon\Carbon;

/**
 * Analytics Request Data Transfer Object
 * SRS Reference: Section 3.6.1 - Analytics Request Structure
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
        $this->setDefaultWidgets();
    }

    /**
     * Create from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cooperativeId: $data['cooperative_id'],
            period: $data['period'] ?? 'monthly',
            widgets: $data['widgets'] ?? [],
            filters: $data['filters'] ?? [],
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            metrics: $data['metrics'] ?? [],
            includeComparisons: $data['include_comparisons'] ?? false,
            includeTrends: $data['include_trends'] ?? true,
            granularity: $data['granularity'] ?? 'daily',
            kpis: $data['kpis'] ?? [],
            userId: $data['user_id'] ?? auth()->id()
        );
    }

    /**
     * Create from request
     */
    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        return self::fromArray($request->all());
    }

    /**
     * Get date range based on period
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
                'from' => $now->copy()->subDays(30),
                'to' => $now
            ],
            'weekly' => [
                'from' => $now->copy()->subWeeks(12),
                'to' => $now
            ],
            'monthly' => [
                'from' => $now->copy()->subMonths(12),
                'to' => $now
            ],
            'quarterly' => [
                'from' => $now->copy()->subQuarters(4),
                'to' => $now
            ],
            'yearly' => [
                'from' => $now->copy()->subYears(3),
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
     * Validate period
     */
    private function validatePeriod(): void
    {
        $validPeriods = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];

        if (!in_array($this->period, $validPeriods)) {
            throw new \InvalidArgumentException("Invalid period: {$this->period}. Must be one of: " . implode(', ', $validPeriods));
        }
    }

    /**
     * Validate date range
     */
    private function validateDateRange(): void
    {
        if ($this->dateFrom && $this->dateTo) {
            $from = Carbon::parse($this->dateFrom);
            $to = Carbon::parse($this->dateTo);

            if ($from->gt($to)) {
                throw new \InvalidArgumentException('Date from cannot be greater than date to');
            }

            if ($from->diffInDays($to) > 365) {
                throw new \InvalidArgumentException('Date range cannot exceed 365 days');
            }
        }
    }

    /**
     * Set default widgets if none provided
     */
    private function setDefaultWidgets(): void
    {
        if (empty($this->widgets)) {
            $this->widgets = [
                'financial_overview',
                'member_growth',
                'savings_trends',
                'loan_portfolio'
            ];
        }
    }
}
