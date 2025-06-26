<?php
// app/Domain/Analytics/DTOs/CreateWidgetDTO.php
namespace App\Domain\Analytics\DTOs;

class CreateWidgetDTO
{
    public function __construct(
        public readonly int $cooperativeId,
        public readonly int $userId,
        public readonly string $widgetType,
        public readonly string $title,
        public readonly array $configuration = [],
        public readonly int $positionX = 0,
        public readonly int $positionY = 0,
        public readonly int $width = 4,
        public readonly int $height = 3,
        public readonly bool $isActive = true,
        public readonly int $refreshInterval = 300
    ) {}
}
