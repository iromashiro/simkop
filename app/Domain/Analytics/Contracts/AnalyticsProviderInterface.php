<?php
// app/Domain/Analytics/Contracts/AnalyticsProviderInterface.php
namespace App\Domain\Analytics\Contracts;

use App\Domain\Analytics\DTOs\AnalyticsRequestDTO;
use App\Domain\Analytics\DTOs\WidgetDataDTO;

/**
 * Analytics Provider Interface
 * SRS Reference: Section 3.6.4 - Analytics Provider Contract
 */
interface AnalyticsProviderInterface
{
    /**
     * Generate analytics widget data
     */
    public function generate(AnalyticsRequestDTO $request): WidgetDataDTO;

    /**
     * Get provider name
     */
    public function getName(): string;

    /**
     * Get provider description
     */
    public function getDescription(): string;

    /**
     * Get required permissions
     */
    public function getRequiredPermissions(): array;

    /**
     * Get cache key for request
     */
    public function getCacheKey(AnalyticsRequestDTO $request): string;

    /**
     * Get cache TTL in seconds
     */
    public function getCacheTTL(): int;

    /**
     * Validate request data
     */
    public function validate(AnalyticsRequestDTO $request): bool;

    /**
     * Get supported metrics
     */
    public function getSupportedMetrics(): array;

    /**
     * Check if provider supports real-time data
     */
    public function supportsRealTime(): bool;

    /**
     * Get provider configuration
     */
    public function getConfiguration(): array;

    /**
     * Get widget type
     */
    public function getWidgetType(): string;

    /**
     * Get default chart configuration
     */
    public function getDefaultChartConfig(): array;

    /**
     * Check if provider can handle specific period
     */
    public function supportsPeriod(string $period): bool;
}
