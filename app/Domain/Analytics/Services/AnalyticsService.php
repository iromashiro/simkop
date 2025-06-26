<?php
// app/Domain/Analytics/Services/AnalyticsService.php
namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\DTOs\AnalyticsRequestDTO;
use App\Domain\Analytics\DTOs\AnalyticsResultDTO;
use App\Domain\Analytics\Contracts\AnalyticsProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Advanced Analytics Service for financial insights
 * SRS Reference: Section 3.6 - Analytics and Dashboard Requirements
 */
class AnalyticsService
{
    private array $providers = [];

    public function __construct()
    {
        $this->registerProviders();
    }

    /**
     * Generate analytics dashboard data
     */
    public function generateDashboard(AnalyticsRequestDTO $request): AnalyticsResultDTO
    {
        $cacheKey = "analytics_dashboard:{$request->cooperativeId}:{$request->period}:" . md5(serialize($request->filters));

        return Cache::remember($cacheKey, 1800, function () use ($request) { // 30 minutes cache
            try {
                Log::info('Generating analytics dashboard', [
                    'cooperative_id' => $request->cooperativeId,
                    'period' => $request->period,
                    'user_id' => auth()->id(),
                ]);

                $startTime = microtime(true);

                // Generate all analytics widgets
                $widgets = [];
                foreach ($request->widgets as $widgetType) {
                    $provider = $this->getProvider($widgetType);
                    $widgets[$widgetType] = $provider->generate($request);
                }

                $executionTime = microtime(true) - $startTime;

                Log::info('Analytics dashboard generated', [
                    'execution_time' => $executionTime,
                    'widgets_count' => count($widgets),
                ]);

                return new AnalyticsResultDTO(
                    widgets: $widgets,
                    metadata: [
                        'generated_at' => now()->toISOString(),
                        'execution_time' => $executionTime,
                        'cache_key' => $cacheKey,
                        'period' => $request->period,
                    ],
                    cooperativeId: $request->cooperativeId
                );
            } catch (\Exception $e) {
                Log::error('Analytics dashboard generation failed', [
                    'error' => $e->getMessage(),
                    'cooperative_id' => $request->cooperativeId,
                ]);
                throw $e;
            }
        });
    }

    /**
     * Register analytics providers
     */
    private function registerProviders(): void
    {
        $this->providers = [
            'financial_overview' => new \App\Domain\Analytics\Providers\FinancialOverviewProvider(),
            'member_growth' => new \App\Domain\Analytics\Providers\MemberGrowthProvider(),
            'savings_trends' => new \App\Domain\Analytics\Providers\SavingsTrendsProvider(),
            'loan_portfolio' => new \App\Domain\Analytics\Providers\LoanPortfolioProvider(),
            'profitability' => new \App\Domain\Analytics\Providers\ProfitabilityProvider(),
            'risk_metrics' => new \App\Domain\Analytics\Providers\RiskMetricsProvider(),
        ];
    }

    /**
     * Get analytics provider
     */
    private function getProvider(string $type): AnalyticsProviderInterface
    {
        if (!isset($this->providers[$type])) {
            throw new \InvalidArgumentException("Unknown analytics provider: {$type}");
        }

        return $this->providers[$type];
    }
}
