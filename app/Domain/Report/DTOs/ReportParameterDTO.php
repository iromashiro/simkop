<?php
// app/Domain/Report/DTOs/ReportParameterDTO.php
namespace App\Domain\Report\DTOs;

use Carbon\Carbon;

/**
 * SECURITY HARDENED: DTO for report parameters with comprehensive validation
 */
class ReportParameterDTO
{
    public function __construct(
        public readonly int $cooperativeId,
        public readonly Carbon $startDate,
        public readonly Carbon $endDate,
        public readonly ?int $fiscalPeriodId = null,
        public readonly ?array $memberIds = null,
        public readonly ?array $accountIds = null,
        public readonly string $format = 'html',
        public readonly array $options = []
    ) {
        $this->validate();
    }

    /**
     * SECURITY FIX: Comprehensive validation with security checks
     */
    private function validate(): void
    {
        // Validate cooperative ID
        if ($this->cooperativeId <= 0) {
            throw new \InvalidArgumentException('Invalid cooperative ID');
        }

        // Validate date range
        if ($this->startDate->gt($this->endDate)) {
            throw new \InvalidArgumentException('Start date cannot be after end date');
        }

        if ($this->startDate->diffInYears($this->endDate) > 5) {
            throw new \InvalidArgumentException('Date range cannot exceed 5 years');
        }

        // SECURITY: Validate date is not too far in the past (prevent data mining)
        if ($this->startDate->lt(now()->subYears(10))) {
            throw new \InvalidArgumentException('Start date cannot be more than 10 years ago');
        }

        // SECURITY: Validate date is not in the future
        if ($this->endDate->gt(now()->addDay())) { // Allow 1 day buffer for timezone issues
            throw new \InvalidArgumentException('End date cannot be in the future');
        }

        // Validate format
        if (!in_array($this->format, ['html', 'pdf', 'excel', 'csv'])) {
            throw new \InvalidArgumentException('Invalid export format');
        }

        // SECURITY: Validate array sizes to prevent DoS
        if ($this->memberIds && count($this->memberIds) > 1000) {
            throw new \InvalidArgumentException('Cannot select more than 1000 members');
        }

        if ($this->accountIds && count($this->accountIds) > 500) {
            throw new \InvalidArgumentException('Cannot select more than 500 accounts');
        }

        // Validate member IDs are numeric and positive
        if ($this->memberIds) {
            foreach ($this->memberIds as $id) {
                if (!is_int($id) || $id <= 0) {
                    throw new \InvalidArgumentException('Invalid member ID provided');
                }
            }
        }

        // Validate account IDs are numeric and positive
        if ($this->accountIds) {
            foreach ($this->accountIds as $id) {
                if (!is_int($id) || $id <= 0) {
                    throw new \InvalidArgumentException('Invalid account ID provided');
                }
            }
        }

        // Validate fiscal period ID
        if ($this->fiscalPeriodId && $this->fiscalPeriodId <= 0) {
            throw new \InvalidArgumentException('Invalid fiscal period ID');
        }

        // SECURITY: Validate options array
        if (!empty($this->options)) {
            $this->validateOptions();
        }
    }

    /**
     * SECURITY: Validate options array for potential security issues
     */
    private function validateOptions(): void
    {
        $allowedOptions = [
            'include_zero_balances',
            'group_by_category',
            'show_details',
            'currency_format',
            'decimal_places',
            'page_size',
            'sort_by',
            'sort_direction',
        ];

        foreach ($this->options as $key => $value) {
            if (!in_array($key, $allowedOptions)) {
                throw new \InvalidArgumentException("Invalid option: {$key}");
            }

            // Validate specific option values
            switch ($key) {
                case 'decimal_places':
                    if (!is_int($value) || $value < 0 || $value > 4) {
                        throw new \InvalidArgumentException('Decimal places must be between 0 and 4');
                    }
                    break;

                case 'page_size':
                    if (!is_int($value) || $value < 10 || $value > 1000) {
                        throw new \InvalidArgumentException('Page size must be between 10 and 1000');
                    }
                    break;

                case 'sort_by':
                    $allowedSortFields = ['code', 'name', 'balance', 'date', 'member_number'];
                    if (!in_array($value, $allowedSortFields)) {
                        throw new \InvalidArgumentException('Invalid sort field');
                    }
                    break;

                case 'sort_direction':
                    if (!in_array(strtolower($value), ['asc', 'desc'])) {
                        throw new \InvalidArgumentException('Sort direction must be asc or desc');
                    }
                    break;
            }
        }
    }

    public static function fromRequest(array $data): self
    {
        // SECURITY: Sanitize input data
        $sanitizedData = [
            'cooperative_id' => (int) ($data['cooperative_id'] ?? 0),
            'start_date' => trim($data['start_date'] ?? ''),
            'end_date' => trim($data['end_date'] ?? ''),
            'fiscal_period_id' => isset($data['fiscal_period_id']) ? (int) $data['fiscal_period_id'] : null,
            'member_ids' => isset($data['member_ids']) ? array_map('intval', (array) $data['member_ids']) : null,
            'account_ids' => isset($data['account_ids']) ? array_map('intval', (array) $data['account_ids']) : null,
            'format' => strtolower(trim($data['format'] ?? 'html')),
            'options' => is_array($data['options'] ?? null) ? $data['options'] : [],
        ];

        // Validate date strings before parsing
        if (empty($sanitizedData['start_date']) || empty($sanitizedData['end_date'])) {
            throw new \InvalidArgumentException('Start date and end date are required');
        }

        try {
            $startDate = Carbon::parse($sanitizedData['start_date']);
            $endDate = Carbon::parse($sanitizedData['end_date']);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date format provided');
        }

        return new self(
            cooperativeId: $sanitizedData['cooperative_id'],
            startDate: $startDate,
            endDate: $endDate,
            fiscalPeriodId: $sanitizedData['fiscal_period_id'],
            memberIds: $sanitizedData['member_ids'],
            accountIds: $sanitizedData['account_ids'],
            format: $sanitizedData['format'],
            options: $sanitizedData['options']
        );
    }

    /**
     * Convert to array for logging/debugging
     */
    public function toArray(): array
    {
        return [
            'cooperative_id' => $this->cooperativeId,
            'start_date' => $this->startDate->toDateString(),
            'end_date' => $this->endDate->toDateString(),
            'fiscal_period_id' => $this->fiscalPeriodId,
            'member_count' => $this->memberIds ? count($this->memberIds) : null,
            'account_count' => $this->accountIds ? count($this->accountIds) : null,
            'format' => $this->format,
            'options' => $this->options,
        ];
    }

    /**
     * Get cache key for this parameter set
     */
    public function getCacheKey(string $reportCode): string
    {
        return sprintf(
            'report:%s:%d:%s:%s:%s',
            $reportCode,
            $this->cooperativeId,
            $this->startDate->format('Y-m-d'),
            $this->endDate->format('Y-m-d'),
            md5(serialize([
                'fiscal_period_id' => $this->fiscalPeriodId,
                'member_ids' => $this->memberIds,
                'account_ids' => $this->accountIds,
                'options' => $this->options,
            ]))
        );
    }
}
