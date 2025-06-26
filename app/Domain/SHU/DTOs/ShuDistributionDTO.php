<?php
// app/Domain/SHU/DTOs/ShuDistributionDTO.php
namespace App\Domain\SHU\DTOs;

use App\Domain\SHU\Models\ShuPlan;
use App\Domain\User\Models\User;
use Carbon\Carbon;

/**
 * DTO for SHU distribution results
 */
class ShuDistributionDTO
{
    public function __construct(
        public readonly ShuPlan $shuPlan,
        public readonly array $memberCalculations,
        public readonly array $summary,
        public readonly Carbon $calculatedAt,
        public readonly User $calculatedBy
    ) {}

    public function toArray(): array
    {
        return [
            'shu_plan' => $this->shuPlan->toArray(),
            'member_calculations' => $this->memberCalculations,
            'summary' => $this->summary,
            'calculated_at' => $this->calculatedAt->toISOString(),
            'calculated_by' => $this->calculatedBy->name,
        ];
    }
}
