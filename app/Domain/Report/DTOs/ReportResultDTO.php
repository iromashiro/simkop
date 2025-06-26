<?php
// app/Domain/Report/DTOs/ReportResultDTO.php
namespace App\Domain\Report\DTOs;

/**
 * DTO for report results with metadata
 */
class ReportResultDTO
{
    public function __construct(
        public readonly string $title,
        public readonly array $data,
        public readonly array $summary,
        public readonly array $metadata,
        public readonly string $generatedAt,
        public readonly string $generatedBy,
        public readonly int $cooperativeId
    ) {}

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'data' => $this->data,
            'summary' => $this->summary,
            'metadata' => $this->metadata,
            'generated_at' => $this->generatedAt,
            'generated_by' => $this->generatedBy,
            'cooperative_id' => $this->cooperativeId,
        ];
    }
}
