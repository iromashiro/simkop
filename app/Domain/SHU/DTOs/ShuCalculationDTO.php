<?php
// app/Domain/SHU/DTOs/ShuCalculationDTO.php
namespace App\Domain\SHU\DTOs;

use App\Domain\SHU\Models\ShuPlan;

/**
 * DTO for SHU calculation parameters
 */
class ShuCalculationDTO
{
    public function __construct(
        public readonly ShuPlan $shuPlan,
        public readonly array $options = []
    ) {}

    public static function fromShuPlan(ShuPlan $shuPlan, array $options = []): self
    {
        return new self($shuPlan, $options);
    }
}
