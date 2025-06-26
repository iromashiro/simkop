<?php
// app/Domain/Analytics/DTOs/UpdateWidgetDTO.php
namespace App\Domain\Analytics\DTOs;

class UpdateWidgetDTO
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?array $configuration = null,
        public readonly ?int $positionX = null,
        public readonly ?int $positionY = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?bool $isActive = null,
        public readonly ?int $refreshInterval = null
    ) {}
}
