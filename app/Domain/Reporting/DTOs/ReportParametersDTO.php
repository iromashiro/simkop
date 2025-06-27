<?php

namespace App\Domain\Reporting\DTOs;

class ReportParametersDTO
{
    public function __construct(
        public readonly int $cooperativeId,
        public readonly string $reportType,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $format = 'pdf',
        public readonly array $filters = [],
        public readonly ?string $email = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            cooperativeId: $data['cooperative_id'],
            reportType: $data['report_type'],
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            format: $data['format'] ?? 'pdf',
            filters: $data['filters'] ?? [],
            email: $data['email'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'cooperative_id' => $this->cooperativeId,
            'report_type' => $this->reportType,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'format' => $this->format,
            'filters' => $this->filters,
            'email' => $this->email,
        ];
    }

    public function isEmailReport(): bool
    {
        return !empty($this->email);
    }

    public function isPdfFormat(): bool
    {
        return $this->format === 'pdf';
    }

    public function isExcelFormat(): bool
    {
        return in_array($this->format, ['excel', 'xlsx', 'csv']);
    }
}
