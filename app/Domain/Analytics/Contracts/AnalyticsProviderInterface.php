<?php
// app/Domain/Analytics/Contracts/AnalyticsProviderInterface.php
namespace App\Domain\Analytics\Contracts;

use App\Domain\Analytics\DTOs\AnalyticsRequestDTO;

interface AnalyticsProviderInterface
{
    public function generate(AnalyticsRequestDTO $request): array;
    public function getName(): string;
    public function getDescription(): string;
    public function getRequiredPermissions(): array;
    public function getCacheKey(AnalyticsRequestDTO $request): string;
    public function getCacheTTL(): int;
    public function validate(AnalyticsRequestDTO $request): bool;
}
