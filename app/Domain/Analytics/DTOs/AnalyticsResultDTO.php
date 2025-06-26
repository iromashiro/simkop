<?php
// app/Domain/Analytics/DTOs/AnalyticsResultDTO.php
namespace App\Domain\Analytics\DTOs;

class AnalyticsResultDTO
{
    public function __construct(
        public array $widgets,
        public array $metadata,
        public int $cooperativeId,
        public array $kpis = [],
        public array $trends = [],
        public array $comparisons = [],
        public array $alerts = []
    ) {}

    public function toArray(): array
    {
        return [
            'widgets' => $this->widgets,
            'metadata' => $this->metadata,
            'cooperative_id' => $this->cooperativeId,
            'kpis' => $this->kpis,
            'trends' => $this->trends,
            'comparisons' => $this->comparisons,
            'alerts' => $this->alerts,
        ];
    }
}
